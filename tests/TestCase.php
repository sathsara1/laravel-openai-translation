<?php

namespace Sathsara1\LaravelOpenAITranslation\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sathsara1\LaravelOpenAITranslation\OpenAITranslationServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            OpenAITranslationServiceProvider::class,
        ];
    }
}
