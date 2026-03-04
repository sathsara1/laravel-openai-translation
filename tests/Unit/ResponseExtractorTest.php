<?php

namespace Sathsara1\LaravelOpenAITranslation\Tests\Unit;

use Sathsara1\LaravelOpenAITranslation\ResponseExtractor;
use Sathsara1\LaravelOpenAITranslation\Tests\TestCase;

class ResponseExtractorTest extends TestCase
{
    private ResponseExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ResponseExtractor;
    }

    public function test_extracts_content_from_responses_api_format(): void
    {
        $result = [
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Bonjour le monde',
                        ],
                    ],
                ],
            ],
        ];

        $content = $this->extractor->extractMessageContent($result, '/responses');

        $this->assertSame('Bonjour le monde', $content);
    }

    public function test_extracts_content_from_chat_completions_format(): void
    {
        $result = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'Hola mundo',
                    ],
                ],
            ],
        ];

        $content = $this->extractor->extractMessageContent($result, '/chat/completions');

        $this->assertSame('Hola mundo', $content);
    }

    public function test_returns_empty_string_for_responses_api_with_no_message(): void
    {
        $result = [
            'output' => [],
        ];

        $content = $this->extractor->extractMessageContent($result, '/v1/responses');

        $this->assertSame('', $content);
    }

    public function test_returns_empty_string_for_chat_completions_with_no_choices(): void
    {
        $result = [];

        $content = $this->extractor->extractMessageContent($result, '/chat/completions');

        $this->assertSame('', $content);
    }

    public function test_returns_empty_string_for_chat_completions_with_empty_choices_array(): void
    {
        $result = [
            'choices' => [],
        ];

        $content = $this->extractor->extractMessageContent($result, '/chat/completions');

        $this->assertSame('', $content);
    }
}
