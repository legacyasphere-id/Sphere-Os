<?php

namespace App\Services\AI;

use App\AI\AIRequest;
use App\AI\AIResponse;
use App\Contracts\AIServiceContract;
use App\Services\AI\Concerns\CalculatesCost;
use Anthropic\Client;

class AnthropicDriver implements AIServiceContract
{
    use CalculatesCost;

    public function __construct(private readonly Client $client) {}

    public function complete(AIRequest $request): AIResponse
    {
        $start = hrtime(true);

        $response = $this->client->messages()->create([
            'model'      => $request->model,
            'max_tokens' => $request->maxTokens,
            'system'     => $request->systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $request->userPrompt],
            ],
        ]);

        $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

        $promptTokens     = $response->usage->inputTokens;
        $completionTokens = $response->usage->outputTokens;
        $content          = $response->content[0]->text ?? '';

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
