<?php

return [
    'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
    'api_key' => env('OPENAI_API_KEY', ''),
    'model' => env('OPENAI_TRANSLATION_MODEL', 'gpt-4o-mini'),
    'endpoint' => env('OPENAI_TRANSLATION_ENDPOINT', '/responses'),

    /*
    | Allow non-HTTPS or localhost/private URLs (e.g. for local dev or proxies).
    | When false, api_url must be HTTPS and not localhost/private IP.
    */
    'allow_private_urls' => env('OPENAI_ALLOW_PRIVATE_URLS', false),

    /*
    | Prompts - fully customizable. Maps to API instructions (Responses) or messages[0].content (Chat).
    | system_command: System/developer message. Falls back to default_system_command if empty.
    | user_command: Prefix for user content (e.g. "Translate the following text to").
    */
    'system_command' => env('OPENAI_TRANSLATION_SYSTEM_COMMAND', null),
    'user_command' => env('OPENAI_TRANSLATION_USER_COMMAND', 'Translate the following text to'),

    /*
    | Token handling. token_mode: 'auto' | 'manual'
    | - auto: Uses estimation based on text length and language count.
    | - manual: Uses max_tokens directly. Requires max_tokens > 0 when set.
    */
    'token_mode' => env('OPENAI_TRANSLATION_TOKEN_MODE', 'auto'),
    'max_tokens' => env('OPENAI_TRANSLATION_MAX_TOKENS'),

    /*
    | Sampling - direct API params (temperature 0-2, top_p 0-1).
    */
    'temperature' => env('OPENAI_TRANSLATION_TEMPERATURE'),
    'top_p' => env('OPENAI_TRANSLATION_TOP_P'),

    /*
    | HTTP client options.
    */
    'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 5),
    'timeout' => (int) env('OPENAI_TIMEOUT', 60),

    /*
    | Reasoning model effort: low | medium | high (for gpt-5, o-series, etc.).
    */
    'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),

    /*
    | Auto token estimation parameters (used when token_mode is 'auto').
    */
    'auto_token_buffer' => 500,
    'auto_token_reasoning_buffer' => 1500,
    'auto_token_max_output' => 8000,
    'auto_token_max_output_reasoning' => 16000,

    /*
    | Logging. When true, the package logs using log_level.
    */
    'logging' => env('OPENAI_TRANSLATION_LOGGING', false),
    'log_level' => env('OPENAI_TRANSLATION_LOG_LEVEL', 'error'),

    /*
    | Custom model definitions. Add models not in the built-in list.
    | Keys: model ID (e.g. 'gpt-5'), Values: capabilities
    | - reasoning: use reasoning API and max_completion_tokens
    | - json_mode: use structured JSON for multi-language translation
    */
    'models' => [
        // 'my-custom-model' => ['reasoning' => false, 'json_mode' => true],
    ],

    // Default system prompt when OPENAI_TRANSLATION_SYSTEM_COMMAND is not set
    'default_system_command' => <<<'PROMPT'
You are a professional translator. Translate the text accurately while preserving meaning, structure, and tone.
Rules:
- Preserve the original structure and headings exactly.
- Translate faithfully without paraphrasing or adding content.
- Preserve punctuation, formatting, HTML tags, and HTML entities when present.
- Do not translate brand names or text inside quotation marks.
Return ONLY the translation, no explanations.
PROMPT,
];
