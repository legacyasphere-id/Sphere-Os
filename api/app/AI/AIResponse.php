<?php

namespace App\AI;

class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly string $model,
        public readonly float $costUsd,
        public readonly int $latencyMs,
    ) {
        // no-op
    }

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
