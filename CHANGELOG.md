# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0]

### Added

- `TranslationServiceInterface` contract for dependency injection and mocking
- `OpenAITranslation` facade
- Custom exceptions: `TranslationException`, `TranslationConfigException`, `TranslationTimeoutException`, `TranslationApiException`
- Events: `TranslationRequested`, `TranslationCompleted`, `TranslationFailed`
- Configurable logging via `logging` and `log_level` config options
- Configurable timeouts: `connect_timeout`, `timeout` (env: `OPENAI_CONNECT_TIMEOUT`, `OPENAI_TIMEOUT`)
- Configurable reasoning effort: `reasoning_effort` (env: `OPENAI_REASONING_EFFORT`)
- Configurable token estimation: `auto_token_buffer`, `auto_token_reasoning_buffer`, `auto_token_max_output`, `auto_token_max_output_reasoning`
- API URL validation: rejects non-HTTPS and localhost/private IPs unless `OPENAI_ALLOW_PRIVATE_URLS=true`
- API key validation via `requireApiKey()` when making requests

### Changed

- Internal logging now respects `logging` and `log_level` config; events are always dispatched
- Default system prompt updated to general-purpose professional translator (no longer e-commerce focused)

### Removed

- Unused `GptModel` enum

