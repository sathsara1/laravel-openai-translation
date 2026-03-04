<?php

namespace Sathsara1\LaravelOpenAITranslation\Tests\Unit;

use Sathsara1\LaravelOpenAITranslation\Config\OpenAITranslationConfig;
use Sathsara1\LaravelOpenAITranslation\Exceptions\TranslationConfigException;
use Sathsara1\LaravelOpenAITranslation\Tests\TestCase;

class OpenAITranslationConfigTest extends TestCase
{
    public function test_is_reasoning_model_returns_true_for_builtin_reasoning_models(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-5-nano',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
        ]);

        $this->assertTrue($config->isReasoningModel('gpt-5-nano'));
        $this->assertTrue($config->isReasoningModel('gpt-5-mini'));
    }

    public function test_is_reasoning_model_returns_false_for_non_reasoning_models(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
        ]);

        $this->assertFalse($config->isReasoningModel('gpt-4o-mini'));
        $this->assertFalse($config->isReasoningModel('gpt-4o'));
    }

    public function test_custom_model_with_reasoning_capability(): void
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

        $this->assertTrue($config->isReasoningModel('my-custom-model'));
        $this->assertTrue($config->supportsJsonMode('my-custom-model'));
    }

    public function test_unknown_model_falls_back_to_safe_defaults(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
        ]);

        $this->assertFalse($config->isReasoningModel('unknown-future-model'));
        $this->assertFalse($config->supportsJsonMode('unknown-future-model'));
    }

    public function test_get_token_limit_param_responses_api(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
        ]);

        $this->assertSame('max_output_tokens', $config->getTokenLimitParam('gpt-4o-mini', '/responses'));
        $this->assertSame('max_output_tokens', $config->getTokenLimitParam('gpt-5-nano', '/v1/responses'));
    }

    public function test_get_token_limit_param_chat_completions(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/chat/completions',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
        ]);

        $this->assertSame('max_tokens', $config->getTokenLimitParam('gpt-4o-mini', '/chat/completions'));
        $this->assertSame('max_completion_tokens', $config->getTokenLimitParam('gpt-5-nano', '/chat/completions'));
    }

    public function test_get_token_value_manual_mode_returns_max_tokens(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
            'token_mode' => 'manual',
            'max_tokens' => 2048,
        ]);

        $this->assertTrue($config->isManualTokenMode());
        $this->assertSame(2048, $config->getTokenValue('some text', []));
    }

    public function test_get_token_value_manual_mode_returns_null_when_max_tokens_not_set(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
            'token_mode' => 'manual',
        ]);

        $this->assertNull($config->getTokenValue('some text', []));
    }

    public function test_get_token_value_auto_mode_returns_null(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
            'token_mode' => 'auto',
        ]);

        $this->assertTrue($config->isAutoTokenMode());
        $this->assertNull($config->getTokenValue('some text', ['fr', 'de']));
    }

    public function test_from_array_throws_when_api_url_is_http_and_private_urls_disallowed(): void
    {
        $this->expectException(TranslationConfigException::class);
        $this->expectExceptionMessage('HTTPS');

        OpenAITranslationConfig::fromArray([
            'api_url' => 'http://api.example.com/v1',
            'api_key' => 'sk-test',
            'allow_private_urls' => false,
        ]);
    }

    public function test_require_api_key_throws_when_empty(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/responses',
            'system_command' => 'Translate',
            'user_command' => 'Translate to',
        ]);

        $this->expectException(TranslationConfigException::class);
        $this->expectExceptionMessage('API key');

        $config->requireApiKey();
    }

    public function test_config_respects_custom_prompts(): void
    {
        $config = OpenAITranslationConfig::fromArray([
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o-mini',
            'endpoint' => '/responses',
            'system_command' => 'Custom system prompt for translation.',
            'user_command' => 'Translate this to',
        ]);

        $this->assertSame('Custom system prompt for translation.', $config->systemCommand);
        $this->assertSame('Translate this to', $config->userCommand);
    }
}
