<?php

namespace App\Services\AI;

use App\AI\AIRequest;
use App\AI\AIResponse;
use App\Contracts\AIServiceContract;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AIRouter
{
    private OpenAIDriver $openai;
    private AnthropicDriver $anthropic;

    public function __construct(OpenAIDriver $openai, AnthropicDriver $anthropic)
    {
        $this->openai    = $openai;
        $this->anthropic = $anthropic;
    }

    public function complete(AIRequest $request): AIResponse
    {
        $driver   = $this->driverFor($request->model);
        $fallback = $this->fallbackFor($request->taskType);

        try {
            return $driver->complete($request);
        } catch (Throwable $e) {
            Log::warning("AI primary model failed ({$request->model}): {$e->getMessage()}");

            if ($fallback === null || $fallback === $request->model) {
                throw new RuntimeException('AI service unavailable. Please try again later.', 0, $e);
            }

            try {
                $fallbackRequest = new AIRequest(
                    taskType:     $request->taskType,
                    model:        $fallback,
                    systemPrompt: $request->systemPrompt,
                    userPrompt:   $request->userPrompt,
                    temperature:  $request->temperature,
                    maxTokens:    $request->maxTokens,
                );

                return $this->driverFor($fallback)->complete($fallbackRequest);
            } catch (Throwable $e2) {
                Log::error("AI fallback model also failed ({$fallback}): {$e2->getMessage()}");
                throw new RuntimeException('AI service unavailable. Please try again later.', 0, $e2);
            }
        }
    }

    private function driverFor(string $model): AIServiceContract
    {
        return str_starts_with($model, 'claude') ? $this->anthropic : $this->openai;
    }

    private function fallbackFor(string $taskType): ?string
    {
        return config("ai.fallback_model.{$taskType}");
    }
}
