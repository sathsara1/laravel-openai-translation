<?php

namespace Sathsara1\LaravelOpenAITranslation\Contracts;

use Sathsara1\LaravelOpenAITranslation\Exceptions\TranslationException;

interface TranslationServiceInterface
{
    /**
     * Translate text to a single target language.
     *
     * @throws TranslationException
     */
    public function translate(string $text, string $targetLanguageCode): string;

    /**
     * Translate text to multiple languages using parallel chunked requests.
     *
     * @param  array<string>  $targetLanguageCodes
     * @return array<string, string> Language code => translation
     *
     * @throws TranslationException
     */
    public function translateMultiple(string $text, array $targetLanguageCodes, int $chunkSize = 3): array;
}
