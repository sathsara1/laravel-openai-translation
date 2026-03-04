# Laravel OpenAI Translation

OpenAI-powered translation service for Laravel. Supports both **Chat Completions** and **Responses API**, with parallel chunked translation for multiple languages.

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x or 12.x
- GuzzleHTTP 7.x

## Installation

```bash
composer require sathsara1/laravel-openai-translation
```

Publish the config file:

```bash
php artisan vendor:publish --tag=openai-translation-config
```

## Configuration

Add to your `.env`:

```env
OPENAI_API_KEY=sk-your-api-key
OPENAI_API_URL=https://api.openai.com/v1
OPENAI_TRANSLATION_MODEL=gpt-4o-mini
OPENAI_TRANSLATION_ENDPOINT=/responses
```

### Options

| Key | Description | Default |
|-----|-------------|---------|
| `OPENAI_API_KEY` | Your OpenAI API key | (required) |
| `OPENAI_API_URL` | OpenAI API base URL | `https://api.openai.com/v1` |
| `OPENAI_ALLOW_PRIVATE_URLS` | Allow HTTP or localhost (dev only) | `false` |
| `OPENAI_TRANSLATION_MODEL` | Model to use (e.g. `gpt-4o-mini`, `gpt-5-nano`) | `gpt-4o-mini` |
| `OPENAI_TRANSLATION_ENDPOINT` | API endpoint (`/chat/completions` or `/responses`) | `/responses` |
| `OPENAI_TRANSLATION_SYSTEM_COMMAND` | Custom system prompt | General-purpose professional translator |
| `OPENAI_TRANSLATION_USER_COMMAND` | User prompt prefix | `Translate the following text to` |
| `OPENAI_TRANSLATION_TOKEN_MODE` | `auto` (estimation) or `manual` | `auto` |
| `OPENAI_TRANSLATION_MAX_TOKENS` | Max output tokens (when `token_mode` is `manual`) | (none) |
| `OPENAI_TRANSLATION_TEMPERATURE` | Temperature (0–2) | (model default) |
| `OPENAI_TRANSLATION_TOP_P` | Top P (0–1) | (model default) |
| `OPENAI_CONNECT_TIMEOUT` | HTTP connect timeout (seconds) | `5` |
| `OPENAI_TIMEOUT` | HTTP request timeout (seconds) | `60` |
| `OPENAI_REASONING_EFFORT` | Reasoning effort: `low`, `medium`, `high` | `low` |
| `OPENAI_TRANSLATION_LOGGING` | Enable package logging | `false` |
| `OPENAI_TRANSLATION_LOG_LEVEL` | Log level: `debug`, `info`, `warning`, `error` | `error` |

### Security

- **API key**: Keep `OPENAI_API_KEY` in `.env` and never commit it. Use `config:cache` only in production and ensure the cache is secured.
- **API URL validation**: By default, the package rejects non-HTTPS and localhost/private IP URLs. For local development or proxies, set `OPENAI_ALLOW_PRIVATE_URLS=true`.

### Custom models

Add models not in the built-in list via `config/openai-translation.php`:

```php
'models' => [
    'my-custom-model' => [
        'reasoning' => true,
        'json_mode' => true,
    ],
],
```

## Usage

### Using the facade

```php
use Sathsara1\LaravelOpenAITranslation\Facades\OpenAITranslation;

$translation = OpenAITranslation::translate('Hello, world!', 'fr');

$translations = OpenAITranslation::translateMultiple('Hello, world!', ['fr', 'de', 'es']);
// Returns: ['fr' => '...', 'de' => '...', 'es' => '...']
```

### Dependency injection

```php
use Sathsara1\LaravelOpenAITranslation\Contracts\TranslationServiceInterface;

class MyController
{
    public function __construct(
        private TranslationServiceInterface $translator
    ) {}

    public function translate(string $text): string
    {
        return $this->translator->translate($text, 'fr');
    }
}
```

### Custom chunk size

```php
$translations = OpenAITranslation::translateMultiple($text, ['fr', 'de', 'es', 'it', 'pt'], 5);
```

### Exceptions

The package throws typed exceptions for better error handling:

- `TranslationConfigException` – invalid or missing configuration
- `TranslationTimeoutException` – connection or request timeout
- `TranslationApiException` – API errors (rate limit, empty response, parse errors)
- `TranslationException` – base class for all translation errors

```php
use Sathsara1\LaravelOpenAITranslation\Exceptions\TranslationException;

try {
    $translation = OpenAITranslation::translate('Hello', 'fr');
} catch (TranslationException $e) {
    // Handle translation failure
}
```

## Events

The package dispatches Laravel events you can listen to:

| Event | When |
|-------|------|
| `TranslationRequested` | Before an API call (payload: text length, target languages, mode) |
| `TranslationCompleted` | After a successful translation |
| `TranslationFailed` | After a failure (payload: exception, context) |

```php
// In EventServiceProvider or a listener
Event::listen(TranslationCompleted::class, function (TranslationCompleted $event) {
    // Log, track metrics, etc.
});

Event::listen(TranslationFailed::class, function (TranslationFailed $event) {
    // Alert, retry logic, etc.
});
```

## Supported models

Built-in support for: `gpt-3.5-turbo`, `gpt-4`, `gpt-4-turbo`, `gpt-4o`, `gpt-4o-mini`, `gpt-4.1-nano`, `gpt-5-nano`, `gpt-5-mini`, `davinci-002`.

Add custom or future models via the `models` config key.

## API support

- **Chat Completions** (`/chat/completions`) – standard OpenAI API
- **Responses API** (`/v1/responses`) – newer API with reasoning models (gpt-5-nano, gpt-5-mini)

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for release notes.

## License

MIT
