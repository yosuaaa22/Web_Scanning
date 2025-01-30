<?php
return [
    // Never hardcode API keys directly in the configuration
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'.null),

    // Add additional security settings
    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),

    // Optional: Add validation for API key
    'validate_api_key' => true,
];
