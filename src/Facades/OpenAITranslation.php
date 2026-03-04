<?php

namespace Sathsara1\LaravelOpenAITranslation\Facades;

use Illuminate\Support\Facades\Facade;
use Sathsara1\LaravelOpenAITranslation\Contracts\TranslationServiceInterface;

/**
 * @method static string translate(string $text, string $targetLanguageCode)
 * @method static array<string, string> translateMultiple(string $text, array $targetLanguageCodes, int $chunkSize = 3)
 *
 * @see \Sathsara1\LaravelOpenAITranslation\TranslationService
 * @see TranslationServiceInterface
 */
class OpenAITranslation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TranslationServiceInterface::class;
    }
}
