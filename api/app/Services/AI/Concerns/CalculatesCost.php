<?php

namespace App\Services\AI\Concerns;

trait CalculatesCost
{
    protected function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = config('ai.pricing', []);

        // Try exact match, then prefix match (e.g. "gpt-4o-mini-2024-07-18" → "gpt-4o-mini")
        $rates = $pricing[$model] ?? null;
        if ($rates === null) {
            foreach ($pricing as $key => $value) {
                if (str_starts_with($model, $key)) {
                    $rates = $value;
                    break;
                }
            }
        }

        if ($rates === null) {
            return 0.0;
        }

        [$inputRate, $outputRate] = $rates;

        return round(
            ($promptTokens / 1_000_000 * $inputRate) + ($completionTokens / 1_000_000 * $outputRate),
            6
        );
    }
}
