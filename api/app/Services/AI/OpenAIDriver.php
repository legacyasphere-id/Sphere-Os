<?php

namespace App\Services\AI;

use App\AI\AIRequest;
use App\AI\AIResponse;
use App\Contracts\AIServiceContract;
use App\Services\AI\Concerns\CalculatesCost;
use OpenAI\Client;
use Throwable;

class OpenAIDriver implements AIServiceContract
{
    use CalculatesCost;

    public function __construct(private readonly Client $client) {}

    public function complete(AIRequest $request): AIResponse
    {
        $start = hrtime(true);

        $response = $this->client->chat()->create([
            'model'       => $request->model,
            'temperature' => $request->temperature,
            'max_tokens'  => $request->maxTokens,
            'messages'    => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ['role' => 'user',   'content' => $request->userPrompt],
            ],
        ]);

        $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

        $promptTokens     = $response->usage->promptTokens;
        $completionTokens = $response->usage->completionTokens;
        $content          = $response->choices[0]->message->content ?? '';

        return new AIResponse(
            content:           $content,
            promptTokens:      $promptTokens,
            completionTokens:  $completionTokens,
            model:             $response->model,
            costUsd:           $this->calculateCost($response->model, $promptTokens, $completionTokens),
            latencyMs:         $latencyMs,
        );
    }
}
