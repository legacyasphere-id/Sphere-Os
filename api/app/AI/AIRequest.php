<?php

namespace App\AI;

class AIRequest
{
    public function __construct(
        public readonly string $taskType,
        public readonly string $model,
        public readonly string $systemPrompt,
        public readonly string $userPrompt,
        public readonly float $temperature = 0.7,
        public readonly int $maxTokens = 2000,
    ) {}
}
