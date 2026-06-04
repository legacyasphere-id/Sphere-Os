<?php

namespace App\Services\AI;

use App\AI\AIResponse;
use App\Models\AiLog;
use App\Models\AiPrompt;
use Throwable;

class AILogger
{
    public function logSuccess(
        int $userId,
        string $taskType,
        ?string $resourceType,
        ?int $resourceId,
        ?AiPrompt $prompt,
        AIResponse $response,
    ): AiLog {
        return AiLog::create([
            'user_id'           => $userId,
            'task_type'         => $taskType,
            'resource_type'     => $resourceType,
            'resource_id'       => $resourceId,
            'prompt_id'         => $prompt?->id,
            'model'             => $response->model,
            'prompt_tokens'     => $response->promptTokens,
            'completion_tokens' => $response->completionTokens,
            'total_tokens'      => $response->totalTokens(),
            'cost_usd'          => $response->costUsd,
            'latency_ms'        => $response->latencyMs,
            'status'            => 'success',
            'output_preview'    => substr($response->content, 0, 500),
        ]);
    }

    public function logError(
        int $userId,
        string $taskType,
        ?string $resourceType,
        ?int $resourceId,
        string $errorMessage,
        string $status = 'error',
    ): AiLog {
        return AiLog::create([
            'user_id'       => $userId,
            'task_type'     => $taskType,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'model'         => 'unknown',
            'status'        => $status,
            'error_message' => substr($errorMessage, 0, 1000),
        ]);
    }
}
