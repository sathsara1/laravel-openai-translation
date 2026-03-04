<?php

namespace Sathsara1\LaravelOpenAITranslation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string>|null  $languages
     */
    public function __construct(
        public string $targetLanguage,
        public int $textLength,
        public ?array $languages = null,
    ) {}
}
