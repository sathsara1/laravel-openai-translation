<?php

namespace Sathsara1\LaravelOpenAITranslation\Tests\Unit;

use Sathsara1\LaravelOpenAITranslation\Config\OpenAITranslationConfig;
use Sathsara1\LaravelOpenAITranslation\Exceptions\TranslationConfigException;
use Sathsara1\LaravelOpenAITranslation\Tests\TestCase;
use Sathsara1\LaravelOpenAITranslation\TranslationService;

class TranslationServiceTest extends TestCase
{
    public function test_translate_throws_when_config_missing(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => '',
            'endpoint' => '',
            'system_command' => '',
            'user_command' => '',
        ]);

        $service = new TranslationService($config);

        $this->expectException(TranslationConfigException::class);
        $this->expectExceptionMessage('OpenAI configuration is missing');

        $service->translate('Hello', 'fr');
    }

    public function test_translate_throws_when_api_key_missing(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
        ]);

        $service = new TranslationService($config);

        $this->expectException(TranslationConfigException::class);
        $this->expectExceptionMessage('API key');

        $service->translate('Hello', 'fr');
    }

    public function test_translate_multiple_returns_empty_for_empty_languages(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/chat/completions',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
        ]);

        $service = new TranslationService($config);

        $result = $service->translateMultiple('Hello', []);

        $this->assertSame([], $result);
    }

    public function test_config_from_array_uses_defaults(): void
    {
        $config = OpenAITranslationConfig::fromArray([]);

        $this->assertStringContainsString('https://api.openai.com', $config->apiUrl);
        $this->assertSame('gpt-4o-mini', $config->model);
        $this->assertSame('/responses', $config->endpoint);
        $this->assertSame('Translate the following text to', $config->userCommand);
        $this->assertStringContainsString('professional translator', $config->systemCommand);
    }

    public function test_translate_multiple_with_manual_token_mode_returns_empty_for_empty_languages(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/chat/completions',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
            'token_mode' => 'manual',
            'max_tokens' => 2048,
        ]);

        $service = new TranslationService($config);

        $result = $service->translateMultiple('Hello', []);

        $this->assertSame([], $result);
    }

    public function test_translate_multiple_with_custom_model_config(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'my-custom-model',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
            'models' => [
                'my-custom-model' => ['reasoning' => true, 'json_mode' => true],
            ],
        ]);

        $service = new TranslationService($config);

        $result = $service->translateMultiple('Hello', []);

        $this->assertSame([], $result);
    }
}
