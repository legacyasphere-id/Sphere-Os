<?php

return [

    'default_model' => [
        'breakdown' => env('AI_MODEL_BREAKDOWN', 'claude-haiku-4-5-20251001'),
        'proposal'  => env('AI_MODEL_PROPOSAL',  'gpt-4o-mini'),
        'summary'   => env('AI_MODEL_SUMMARY',   'gpt-4o-mini'),
    ],

    'fallback_model' => [
        'breakdown' => env('AI_FALLBACK_BREAKDOWN', 'gpt-4o-mini'),
        'proposal'  => env('AI_FALLBACK_PROPOSAL',  'claude-haiku-4-5-20251001'),
        'summary'   => env('AI_FALLBACK_SUMMARY',   'claude-haiku-4-5-20251001'),
    ],

    'drivers' => [
        'openai'    => ['api_key' => env('OPENAI_API_KEY')],
        'anthropic' => ['api_key' => env('ANTHROPIC_API_KEY')],
    ],

    // USD per 1M tokens [input, output]
    'pricing' => [
        'gpt-4o'                    => [5.00,  15.00],
        'gpt-4o-mini'               => [0.15,   0.60],
        'claude-opus-4-8'           => [15.00,  75.00],
        'claude-sonnet-4-6'         => [3.00,  15.00],
        'claude-haiku-4-5-20251001' => [0.80,   4.00],
    ],

    'limits' => [
        'max_context_tokens'   => 3000,
        'timeout_seconds'      => 30,
        'daily_calls_per_user' => 100,
        'rate_per_minute'      => 20,
    ],

    'enabled' => env('FEATURE_AI_ENABLED', false),
];
