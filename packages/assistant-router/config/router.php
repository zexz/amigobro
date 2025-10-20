<?php

return [
    'llm' => [
        'provider'    => env('LLM_PROVIDER', 'openai'),
        'model'       => env('LLM_MODEL', 'gpt-5-mini'),
        'timeout'     => 1.5,
        'temperature' => 0.0,
    ],
    'thresholds' => [
        'rule_strong' => 0.80,
        'llm_use'     => 0.70,
        'fallback'    => 0.60,
    ],
];
