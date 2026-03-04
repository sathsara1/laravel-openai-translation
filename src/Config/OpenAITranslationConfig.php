<?php

namespace Sathsara1\LaravelOpenAITranslation\Config;

use Sathsara1\LaravelOpenAITranslation\Exceptions\TranslationConfigException;

class OpenAITranslationConfig
{
    /** @var array<string, array{reasoning?: bool, json_mode?: bool}> */
    private array $modelCapabilities = [];

    /**
     * @param  array<string, array{reasoning?: bool, json_mode?: bool}>  $models
     */
    public function __construct(
        public string $apiUrl,
        public string $apiKey,
        public string $model,
        public string $systemCommand,
        public string $userCommand,
        public string $endpoint,
        public string $tokenMode,
        public int $connectTimeout,
        public int $timeout,
        public string $reasoningEffort,
        public int $autoTokenBuffer,
        public int $autoTokenReasoningBuffer,
        public int $autoTokenMaxOutput,
        public int $autoTokenMaxOutputReasoning,
        public bool $logging,
        public string $logLevel,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?int $maxTokens = null,
        array $models = [],
    ) {
        $this->modelCapabilities = array_merge(
            $this->builtInModelCapabilities(),
            $models
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $apiUrl = rtrim($config['api_url'] ?? 'https://api.openai.com/v1', '/');
        $allowPrivateUrls = (bool) ($config['allow_private_urls'] ?? false);

        self::validateApiUrl($apiUrl, $allowPrivateUrls);

        $reasoningEffort = $config['reasoning_effort'] ?? 'low';
        $validEfforts = ['low', 'medium', 'high'];
        if (! in_array($reasoningEffort, $validEfforts, true)) {
            $reasoningEffort = 'low';
        }

        return new self(
            apiUrl: $apiUrl,
            apiKey: $config['api_key'] ?? '',
            model: $config['model'] ?? 'gpt-4o-mini',
            systemCommand: ! empty($config['system_command'])
                ? $config['system_command']
                : ($config['default_system_command'] ?? self::defaultSystemCommand()),
            userCommand: $config['user_command'] ?? 'Translate the following text to',
            endpoint: $config['endpoint'] ?? '/responses',
            tokenMode: in_array($config['token_mode'] ?? 'auto', ['auto', 'manual'], true)
                ? ($config['token_mode'] ?? 'auto')
                : 'auto',
            connectTimeout: (int) ($config['connect_timeout'] ?? 5),
            timeout: (int) ($config['timeout'] ?? 60),
            reasoningEffort: $reasoningEffort,
            autoTokenBuffer: (int) ($config['auto_token_buffer'] ?? 500),
            autoTokenReasoningBuffer: (int) ($config['auto_token_reasoning_buffer'] ?? 1500),
            autoTokenMaxOutput: (int) ($config['auto_token_max_output'] ?? 8000),
            autoTokenMaxOutputReasoning: (int) ($config['auto_token_max_output_reasoning'] ?? 16000),
            logging: (bool) ($config['logging'] ?? false),
            logLevel: in_array($config['log_level'] ?? 'error', ['debug', 'info', 'warning', 'error'], true)
                ? ($config['log_level'] ?? 'error')
                : 'error',
            temperature: isset($config['temperature']) ? (float) $config['temperature'] : null,
            topP: isset($config['top_p']) ? (float) $config['top_p'] : null,
            maxTokens: isset($config['max_tokens']) && $config['max_tokens'] !== null && $config['max_tokens'] !== ''
                ? (int) $config['max_tokens']
                : null,
            models: $config['models'] ?? [],
        );
    }

    /**
     * @throws TranslationConfigException
     */
    private static function validateApiUrl(string $url, bool $allowPrivateUrls): void
    {
        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            throw new TranslationConfigException('Invalid OpenAI API URL format.');
        }

        if (! $allowPrivateUrls) {
            if (strtolower($parsed['scheme']) !== 'https') {
                throw new TranslationConfigException('OpenAI API URL must use HTTPS. Set OPENAI_ALLOW_PRIVATE_URLS=true for development.');
            }

            $host = strtolower($parsed['host']);
            if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                throw new TranslationConfigException('OpenAI API URL cannot be localhost. Set OPENAI_ALLOW_PRIVATE_URLS=true for development.');
            }

            if (preg_match('/^10\.|^172\.(1[6-9]|2[0-9]|3[0-1])\.|^192\.168\./', $host)) {
                throw new TranslationConfigException('OpenAI API URL cannot be a private IP. Set OPENAI_ALLOW_PRIVATE_URLS=true for development.');
            }
        }
    }

    public function requireApiKey(): void
    {
        if (trim($this->apiKey) === '') {
            throw new TranslationConfigException('OpenAI API key is not configured. Set OPENAI_API_KEY in your .env file.');
        }
    }

    public function isReasoningModel(string $model): bool
    {
        return (bool) ($this->modelCapabilities[$model]['reasoning'] ?? false);
    }

    public function supportsJsonMode(string $model): bool
    {
        return (bool) ($this->modelCapabilities[$model]['json_mode'] ?? false);
    }

    public function getTokenLimitParam(string $model, ?string $endpoint): string
    {
        if ($endpoint !== null && str_contains($endpoint, 'responses')) {
            return 'max_output_tokens';
        }

        return $this->isReasoningModel($model) ? 'max_completion_tokens' : 'max_tokens';
    }

    /**
     * Resolve token value based on token_mode.
     * - manual: returns max_tokens if set and > 0.
     * - auto: returns null (caller should use estimation).
     *
     * @param  array<string>  $languageChunk  Used by caller for estimation when auto mode; not used here.
     */
    public function getTokenValue(string $text, array $languageChunk = []): ?int
    {
        if ($this->tokenMode === 'manual' && $this->maxTokens !== null && $this->maxTokens > 0) {
            return $this->maxTokens;
        }

        return null;
    }

    public function isManualTokenMode(): bool
    {
        return $this->tokenMode === 'manual';
    }

    public function isAutoTokenMode(): bool
    {
        return $this->tokenMode === 'auto';
    }

    /**
     * @return array<string, array{reasoning: bool, json_mode: bool}>
     */
    private function builtInModelCapabilities(): array
    {
        return [
            'gpt-3.5-turbo' => ['reasoning' => false, 'json_mode' => true],
            'gpt-4' => ['reasoning' => false, 'json_mode' => true],
            'gpt-4-turbo' => ['reasoning' => false, 'json_mode' => true],
            'gpt-4-turbo-2024-04-09' => ['reasoning' => false, 'json_mode' => true],
            'gpt-4o' => ['reasoning' => false, 'json_mode' => true],
            'gpt-4o-mini' => ['reasoning' => false, 'json_mode' => true],
            'gpt-4.1-nano' => ['reasoning' => false, 'json_mode' => true],
            'gpt-5-nano' => ['reasoning' => true, 'json_mode' => true],
            'gpt-5-mini' => ['reasoning' => true, 'json_mode' => true],
            'davinci-002' => ['reasoning' => false, 'json_mode' => true],
        ];
    }

    private static function defaultSystemCommand(): string
    {
        return <<<'PROMPT'
You are a professional translator. Translate the text accurately while preserving meaning, structure, and tone.
Rules:
- Preserve the original structure and headings exactly.
- Translate faithfully without paraphrasing or adding content.
- Preserve punctuation, formatting, HTML tags, and HTML entities when present.
- Do not translate brand names or text inside quotation marks.
Return ONLY the translation, no explanations.
PROMPT;
    }
}
