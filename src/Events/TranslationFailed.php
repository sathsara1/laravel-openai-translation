<?php

namespace Sathsara1\LaravelOpenAITranslation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TranslationFailed
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string>|null  $languages
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Throwable $exception,
        public ?string $targetLanguage = null,
        public ?array $languages = null,
        public array $context = [],
    ) {}
}
