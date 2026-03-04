<?php

namespace Sathsara1\LaravelOpenAITranslation;

use Illuminate\Support\ServiceProvider;
use Sathsara1\LaravelOpenAITranslation\Config\OpenAITranslationConfig;
use Sathsara1\LaravelOpenAITranslation\Contracts\TranslationServiceInterface;
use Sathsara1\LaravelOpenAITranslation\Facades\OpenAITranslation;

class OpenAITranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/openai-translation.php',
            'openai-translation'
        );

        $this->app->singleton(TranslationService::class, function ($app) {
            $config = OpenAITranslationConfig::fromArray(
                $app['config']->get('openai-translation', [])
            );

            return new TranslationService($config);
        });

        $this->app->alias(TranslationService::class, TranslationServiceInterface::class);
        $this->app->alias(TranslationService::class, OpenAITranslation::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/openai-translation.php' => config_path('openai-translation.php'),
            ], 'openai-translation-config');
        }
    }
}
