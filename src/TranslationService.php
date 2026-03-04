<?php

namespace Sathsara1\LaravelOpenAITranslation;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Sathsara1\LaravelOpenAITranslation\Config\OpenAITranslationConfig;
use Sathsara1\LaravelOpenAITranslation\Contracts\TranslationServiceInterface;
use Sathsara1\LaravelOpenAITranslation\Events\TranslationCompleted;
use Sathsara1\LaravelOpenAITranslation\Events\TranslationFailed;
use Sathsara1\LaravelOpenAITranslation\Events\TranslationRequested;
use Sathsara1\LaravelOpenAITranslation\Exceptions\TranslationApiException;
use Sathsara1\LaravelOpenAITranslation\Exceptions\TranslationConfigException;
use Sathsara1\LaravelOpenAITranslation\Exceptions\TranslationException;
use Sathsara1\LaravelOpenAITranslation\Exceptions\TranslationTimeoutException;
use Throwable;

class TranslationService implements TranslationServiceInterface
{
    public function __construct(
        protected OpenAITranslationConfig $config,
        protected ?Client $client = null,
        protected ?ResponseExtractor $responseExtractor = null,
    ) {
        $this->client ??= new Client;
        $this->responseExtractor ??= new ResponseExtractor;
    }

    private function usesResponsesApi(string $endpoint): bool
    {
        return str_contains($endpoint, 'responses');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractMessageContent(array $result, string $endpoint): string
    {
        return $this->responseExtractor->extractMessageContent($result, $endpoint);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $message, array $context = [], ?string $level = null): void
    {
        if (! $this->config->logging) {
            return;
        }

        $level = $level ?? $this->config->logLevel;
        $method = match ($level) {
            'debug' => 'debug',
            'info' => 'info',
            'warning' => 'warning',
            default => 'error',
        };

        Log::{$method}($message, $context);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        $url = $this->config->apiUrl.$endpoint;
        $response = $this->client->request('POST', $url, [
            'json' => $data,
            'headers' => [
                'Authorization' => 'Bearer '.$this->config->apiKey,
                'Content-Type' => 'application/json',
            ],
            'connect_timeout' => $this->config->connectTimeout,
            'timeout' => $this->config->timeout,
            'curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0],
        ]);

        $body = $response->getBody()->getContents();

        return json_decode($body, true) ?? [];
    }

    /**
     * Translate text to a single target language.
     *
     * @throws TranslationException
     */
    public function translate(string $text, string $targetLanguageCode): string
    {
        $model = $this->config->model;
        $systemCommand = $this->config->systemCommand;
        $userCommand = $this->config->userCommand;
        $endpoint = $this->config->endpoint;

        if (empty($model) || empty($userCommand) || empty($systemCommand) || empty($endpoint)) {
            throw new TranslationConfigException('OpenAI configuration is missing (model, user_command, system_command, endpoint required)');
        }

        $this->config->requireApiKey();

        Event::dispatch(new TranslationRequested(strlen($text), [$targetLanguageCode], 'single'));

        try {
            $userContent = "{$userCommand} {$targetLanguageCode}: {$text}";
            $requestData = $this->buildSingleTranslateRequest($userContent);

            $tokenValue = $this->resolveTokenValue($text, [$targetLanguageCode]);
            if ($tokenValue !== null) {
                $tokenParam = $this->config->getTokenLimitParam($model, $endpoint);
                $requestData[$tokenParam] = $tokenValue;
            }
            if ($this->config->temperature !== null) {
                $requestData['temperature'] = $this->config->temperature;
            }
            if ($this->config->topP !== null) {
                $requestData['top_p'] = $this->config->topP;
            }

            $this->log('TranslationService::translate request', ['endpoint' => $endpoint, 'model' => $model]);

            $result = $this->makeRequest($endpoint, $requestData);
            $translation = $this->extractMessageContent($result, $endpoint);

            if (empty($translation)) {
                $this->log("Translation empty for language {$targetLanguageCode}", [], 'warning');
                throw new TranslationApiException('Empty translation.');
            }

            Event::dispatch(new TranslationCompleted($targetLanguageCode, strlen($text)));

            return $translation;
        } catch (ConnectException $e) {
            $this->log("Translation timeout for language {$targetLanguageCode}", ['exception' => $e->getMessage()]);
            Event::dispatch(new TranslationFailed($e, $targetLanguageCode));
            throw new TranslationTimeoutException('Translation timed out. Please try again later.', 0, $e);
        } catch (RequestException $e) {
            $this->log("Translation request failed for language {$targetLanguageCode}", ['exception' => $e->getMessage()]);
            Event::dispatch(new TranslationFailed($e, $targetLanguageCode));
            throw new TranslationApiException('Translation request failed. Please check your internet connection and try again.', 0, $e);
        } catch (TranslationException $e) {
            Event::dispatch(new TranslationFailed($e, $targetLanguageCode));
            throw $e;
        } catch (Throwable $e) {
            $this->log("Translation failed for language {$targetLanguageCode}", ['exception' => $e->getMessage()]);
            Event::dispatch(new TranslationFailed($e, $targetLanguageCode));
            throw new TranslationException('Translation failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Translate text to a chunk of languages in a single API request.
     *
     * @param  array<string>  $languageChunk
     *
     * @throws TranslationException
     */
    protected function translateChunk(string $text, array $languageChunk): PromiseInterface
    {
        $model = $this->config->model;
        $endpoint = $this->config->endpoint;

        if (empty($languageChunk)) {
            return new FulfilledPromise([]);
        }

        $cleanedText = trim(preg_replace('/\s+/', ' ', $text) ?: $text);
        $languagesList = implode(',', $languageChunk);
        $prompt = "Translate the following text to {$languagesList}.Return a JSON object with the language codes as keys.Example:{\"fr\": \"translation\"}.Text:{$cleanedText}";

        $requestData = $this->buildChunkTranslateRequest($prompt, $languageChunk);
        $supportsJsonMode = $this->config->supportsJsonMode($model);

        $tokenValue = $this->resolveTokenValue($cleanedText, $languageChunk);
        if ($tokenValue !== null) {
            $tokenParam = $this->config->getTokenLimitParam($model, $endpoint);
            $requestData[$tokenParam] = $tokenValue;
        }
        if ($this->config->temperature !== null) {
            $requestData['temperature'] = $this->config->temperature;
        }
        if ($this->config->topP !== null) {
            $requestData['top_p'] = $this->config->topP;
        }

        $fullUrl = $this->config->apiUrl.$endpoint;
        $requestOptions = [
            'json' => $requestData,
            'headers' => [
                'Authorization' => 'Bearer '.$this->config->apiKey,
                'Content-Type' => 'application/json',
            ],
            'connect_timeout' => $this->config->connectTimeout,
            'timeout' => $this->config->timeout,
            'curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0],
        ];

        $this->log('TranslationService::translateChunk request', [
            'endpoint' => $endpoint,
            'model' => $model,
            'languages' => $languageChunk,
        ]);

        $promise = $this->client->requestAsync('POST', $fullUrl, $requestOptions);

        return $promise->then(function ($response) use ($languageChunk, $supportsJsonMode, $endpoint) {
            $result = json_decode($response->getBody()->getContents(), true) ?? [];
            $responseContent = $this->extractMessageContent($result, $endpoint);

            if (empty($responseContent)) {
                $firstChoice = ($result['choices'] ?? [])[0] ?? [];
                $finishReason = $firstChoice['finish_reason'] ?? $result['status'] ?? 'unknown';
                $this->log('Translation empty for chunk', [
                    'languages' => $languageChunk,
                    'finish_reason' => $finishReason,
                ], 'warning');
                throw new TranslationApiException('Empty translation response for chunk: '.implode(', ', $languageChunk));
            }

            $jsonContent = $responseContent;
            if (! $supportsJsonMode) {
                $jsonStart = strpos($responseContent, '{');
                if ($jsonStart !== false) {
                    $jsonEnd = strrpos($responseContent, '}');
                    if ($jsonEnd !== false && $jsonEnd > $jsonStart) {
                        $jsonContent = substr($responseContent, $jsonStart, $jsonEnd - $jsonStart + 1);
                    }
                }
            }

            $translations = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($translations)) {
                $isTruncated = ! str_ends_with(trim($jsonContent), '}');
                if ($isTruncated) {
                    throw new TranslationApiException('Translation response truncated for chunk: '.implode(', ', $languageChunk));
                }
                $jsonContent = preg_replace('/,\s*([}\]])/', '$1', $jsonContent);
                $translations = json_decode($jsonContent, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($translations)) {
                    throw new TranslationApiException('Failed to parse JSON for chunk: '.implode(', ', $languageChunk).' - '.json_last_error_msg());
                }
            }

            $output = [];
            foreach ($languageChunk as $code) {
                if (isset($translations[$code])) {
                    $output[$code] = $translations[$code];
                } else {
                    $found = false;
                    foreach ($translations as $key => $value) {
                        if (strtolower((string) $key) === strtolower($code) || str_contains(strtolower((string) $key), strtolower($code))) {
                            $output[$code] = $value;
                            $found = true;
                            break;
                        }
                    }
                    if (! $found) {
                        throw new TranslationApiException("Translation missing for language code: {$code}");
                    }
                }
            }

            return $output;
        });
    }

    /**
     * Translate text to multiple languages using parallel chunked requests.
     *
     * @param  array<string>  $targetLanguageCodes
     * @return array<string, string> Language code => translation
     *
     * @throws TranslationException
     */
    public function translateMultiple(string $text, array $targetLanguageCodes, int $chunkSize = 3): array
    {
        $model = $this->config->model;
        $endpoint = $this->config->endpoint;

        if (empty($model) || empty($endpoint)) {
            throw new TranslationConfigException('OpenAI configuration is missing (model and endpoint required)');
        }

        $this->config->requireApiKey();

        if (empty($targetLanguageCodes)) {
            return [];
        }

        Event::dispatch(new TranslationRequested(strlen($text), $targetLanguageCodes, 'multiple'));

        if (count($targetLanguageCodes) <= $chunkSize) {
            $chunkSize = count($targetLanguageCodes);
        }

        $chunks = array_chunk($targetLanguageCodes, $chunkSize);
        $startTime = microtime(true);

        try {
            $promises = [];
            foreach ($chunks as $index => $chunk) {
                $promises[$index] = $this->translateChunk($text, $chunk);
            }

            $settled = Utils::settle($promises)->wait();
            $allTranslations = [];
            $errors = [];

            foreach ($settled as $index => $result) {
                $chunk = $chunks[$index];
                $chunkLanguages = implode(', ', $chunk);

                if ($result['state'] === 'fulfilled') {
                    $allTranslations = array_merge($allTranslations, $result['value']);
                    $this->log("Chunk {$index} completed successfully", [
                        'chunk_languages' => $chunkLanguages,
                        'translations_count' => count($result['value']),
                    ]);
                } else {
                    $reason = $result['reason'] ?? null;
                    $errorMessage = $reason instanceof Throwable ? $reason->getMessage() : 'Unknown error';
                    $errors[] = "Chunk {$index} ({$chunkLanguages}): {$errorMessage}";
                    $this->log("Chunk {$index} failed", [
                        'chunk_languages' => $chunkLanguages,
                        'error' => $errorMessage,
                    ]);
                }
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->log('Parallel translation completed', [
                'total_languages' => count($targetLanguageCodes),
                'successful_translations' => count($allTranslations),
                'failed_chunks' => count($errors),
                'duration_seconds' => $duration,
                'model' => $model,
            ]);

            if (empty($allTranslations) && ! empty($errors)) {
                throw new TranslationApiException('All translation chunks failed: '.implode('; ', $errors));
            }

            if (! empty($errors)) {
                $this->log('Partial translation success', [
                    'successful' => count($allTranslations),
                    'failed_chunks' => count($errors),
                    'errors' => $errors,
                ], 'warning');
            }

            Event::dispatch(new TranslationCompleted('', strlen($text), $targetLanguageCodes));

            return $allTranslations;
        } catch (TranslationException $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $this->log('Parallel translation failed', [
                'languages' => $targetLanguageCodes,
                'duration_seconds' => $duration,
                'error' => $e->getMessage(),
            ]);
            Event::dispatch(new TranslationFailed($e, null, $targetLanguageCodes, [
                'duration_seconds' => $duration,
            ]));
            throw new TranslationException('Translation failed: '.$e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $this->log('Parallel translation failed', [
                'languages' => $targetLanguageCodes,
                'duration_seconds' => $duration,
                'error' => $e->getMessage(),
            ]);
            Event::dispatch(new TranslationFailed($e, null, $targetLanguageCodes, [
                'duration_seconds' => $duration,
            ]));
            throw new TranslationException('Translation failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Build request payload for single-language translation.
     *
     * @return array<string, mixed>
     */
    private function buildSingleTranslateRequest(string $userContent): array
    {
        $model = $this->config->model;
        $endpoint = $this->config->endpoint;
        $usesResponses = $this->usesResponsesApi($endpoint);

        if ($usesResponses) {
            return [
                'model' => $model,
                'instructions' => $this->config->systemCommand,
                'input' => $userContent,
                'text' => [
                    'verbosity' => 'high',
                    'format' => ['type' => 'text'],
                ],
            ];
        }

        return [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $this->config->systemCommand],
                ['role' => 'user', 'content' => $userContent],
            ],
        ];
    }

    /**
     * Build request payload for chunk (multi-language) translation.
     *
     * @param  array<string>  $languageChunk
     * @return array<string, mixed>
     */
    private function buildChunkTranslateRequest(string $prompt, array $languageChunk): array
    {
        $model = $this->config->model;
        $endpoint = $this->config->endpoint;
        $usesResponses = $this->usesResponsesApi($endpoint);

        if ($usesResponses) {
            $requestData = [
                'model' => $model,
                'instructions' => $this->config->systemCommand,
                'input' => $prompt,
                'text' => ['verbosity' => 'medium'],
            ];
        } else {
            $requestData = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->config->systemCommand],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];
        }

        if ($this->config->isReasoningModel($model)) {
            $requestData['reasoning'] = ['effort' => $this->config->reasoningEffort];
            $requestData['text'] = $requestData['text'] ?? [];
            $requestData['text']['verbosity'] = 'medium';
        } else {
            $requestData['temperature'] = 0;
        }

        $supportsJsonMode = $this->config->supportsJsonMode($model);
        if ($supportsJsonMode) {
            if ($usesResponses) {
                $properties = [];
                foreach ($languageChunk as $code) {
                    $properties[$code] = ['type' => 'string'];
                }
                $requestData['text'] = array_merge($requestData['text'] ?? [], [
                    'verbosity' => 'high',
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'translations',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => $properties,
                            'required' => $languageChunk,
                            'additionalProperties' => false,
                        ],
                    ],
                ]);
            } else {
                $requestData['response_format'] = ['type' => 'json_object'];
            }
        }

        return $requestData;
    }

    /**
     * Resolve token value: manual uses config max_tokens; auto uses estimation.
     *
     * @param  array<string>  $languageChunk
     */
    private function resolveTokenValue(string $text, array $languageChunk = []): ?int
    {
        $model = $this->config->model;
        $tokenValue = $this->config->getTokenValue($text, $languageChunk);

        if ($tokenValue !== null) {
            return $tokenValue;
        }

        if (! $this->config->isAutoTokenMode()) {
            return null;
        }

        $languageCount = max(1, count($languageChunk));
        $estimatedInputTokens = (int) ceil(strlen($text) / 4);
        $estimatedOutputPerLanguage = (int) ceil($estimatedInputTokens * 1.4);
        $estimatedTotalOutput = $estimatedOutputPerLanguage * $languageCount;
        $buffer = $this->config->autoTokenBuffer;

        if ($this->config->isReasoningModel($model)) {
            $reasoningBuffer = $this->config->autoTokenReasoningBuffer;

            return min(
                $this->config->autoTokenMaxOutputReasoning,
                $reasoningBuffer + $estimatedTotalOutput + $buffer
            );
        }

        return min($this->config->autoTokenMaxOutput, $estimatedTotalOutput + $buffer);
    }
}
