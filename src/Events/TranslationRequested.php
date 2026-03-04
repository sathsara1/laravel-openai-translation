<?php

namespace Sathsara1\LaravelOpenAITranslation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationRequested
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string>  $targetLanguages
     */
    public function __construct(
        public int $textLength,
        public array $targetLanguages,
        public string $mode = 'single', // 'single' | 'multiple'
    ) {}
}
