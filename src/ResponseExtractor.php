<?php

namespace Sathsara1\LaravelOpenAITranslation;

class ResponseExtractor
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function extractMessageContent(array $result, string $endpoint): string
    {
        if ($this->usesResponsesApi($endpoint)) {
            return $this->extractFromResponsesApi($result);
        }

        return $this->extractFromChatCompletions($result);
    }

    private function usesResponsesApi(string $endpoint): bool
    {
        return str_contains($endpoint, 'responses');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractFromResponsesApi(array $result): string
    {
        $output = $result['output'] ?? [];
        foreach ($output as $item) {
            if (($item['type'] ?? '') === 'message' && ($item['role'] ?? '') === 'assistant') {
                $content = $item['content'] ?? [];
                foreach (is_array($content) ? $content : [] as $block) {
                    if (($block['type'] ?? '') === 'output_text' && isset($block['text'])) {
                        return (string) $block['text'];
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractFromChatCompletions(array $result): string
    {
        $choices = $result['choices'] ?? [];
        $firstChoice = $choices[0] ?? null;

        if ($firstChoice === null) {
            return '';
        }

        return $firstChoice['message']['content'] ?? '';
    }
}
