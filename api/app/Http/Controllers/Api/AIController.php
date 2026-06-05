<?php

namespace App\Http\Controllers\Api;

use App\AI\AIRequest;
use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\Proposal;
use App\Services\AI\AILogger;
use App\Services\AI\AIRouter;
use App\Services\AI\ContextBuilder;
use App\Services\AI\PromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AIController extends Controller
{
    public function __construct(
        private readonly AIRouter       $router,
        private readonly PromptService  $prompts,
        private readonly ContextBuilder $context,
        private readonly AILogger       $logger,
    ) {}

    public function breakTasks(Request $request): JsonResponse
    {
        if (! config('ai.enabled')) {
            return response()->json(['message' => 'AI not configured', 'fallback' => true], 503);
        }

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'focus'      => ['nullable', 'string', 'max:200'],
        ]);

        $project = Project::where('id', $validated['project_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $userId = $request->user()->id;

        try {
            $prompt  = $this->prompts->get('breakdown');
            $ctx     = $this->buildCachedContext('project', $project->id, $userId, fn () => $this->context->forProject($project));
            $ctx['focus'] = $validated['focus'] ?? 'general';

            $aiRequest = new AIRequest(
                taskType:     'breakdown',
                model:        config('ai.default_model.breakdown'),
                systemPrompt: $prompt->system_message,
                userPrompt:   $this->prompts->render($prompt, $ctx),
                temperature:  0.7,
                maxTokens:    1500,
            );

            $response = $this->router->complete($aiRequest);

            $this->logger->logSuccess($userId, 'breakdown', 'project', $project->id, $prompt, $response);

            $tasks = $this->parseJsonOutput($response->content);

            return response()->json([
                'tasks' => $tasks,
                'meta'  => [
                    'model'       => $response->model,
                    'tokens_used' => $response->totalTokens(),
                    'cost_usd'    => $response->costUsd,
                    'latency_ms'  => $response->latencyMs,
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->logError($userId, 'breakdown', 'project', $project->id, $e->getMessage());

            return response()->json([
                'message'  => 'AI service unavailable. Please try again or create tasks manually.',
                'fallback' => true,
            ], 503);
        }
    }

    public function generateProposal(Request $request): JsonResponse
    {
        if (! config('ai.enabled')) {
            return response()->json(['message' => 'AI not configured', 'fallback' => true], 503);
        }

        $validated = $request->validate([
            'project_id'      => ['required', 'integer', 'exists:projects,id'],
            'tone'            => ['nullable', 'string', 'in:professional,casual,formal'],
            'include_pricing' => ['nullable', 'boolean'],
            'save_as_draft'   => ['nullable', 'boolean'],
            'title'           => ['nullable', 'string', 'max:255'],
        ]);

        $project = Project::where('id', $validated['project_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $userId = $request->user()->id;

        try {
            $prompt = $this->prompts->get('proposal');
            $ctx    = $this->buildCachedContext('project', $project->id, $userId, fn () => $this->context->forProject($project));
            $ctx['tone']            = $validated['tone'] ?? 'professional';
            $ctx['include_pricing'] = ($validated['include_pricing'] ?? false) ? 'yes' : 'no';

            $aiRequest = new AIRequest(
                taskType:     'proposal',
                model:        config('ai.default_model.proposal'),
                systemPrompt: $prompt->system_message,
                userPrompt:   $this->prompts->render($prompt, $ctx),
                temperature:  0.8,
                maxTokens:    2000,
            );

            $response = $this->router->complete($aiRequest);

            $this->logger->logSuccess($userId, 'proposal', 'project', $project->id, $prompt, $response);

            $savedProposal = null;
            if ($validated['save_as_draft'] ?? false) {
                $savedProposal = Proposal::create([
                    'user_id'      => $userId,
                    'project_id'   => $project->id,
                    'client_id'    => $project->client_id,
                    'title'        => $validated['title'] ?? "Proposal for {$project->name}",
                    'content'      => $response->content,
                    'status'       => 'draft',
                    'ai_generated' => true,
                ]);
            }

            return response()->json([
                'proposal'       => $response->content,
                'saved_proposal' => $savedProposal ? ['id' => $savedProposal->id, 'title' => $savedProposal->title, 'status' => $savedProposal->status] : null,
                'meta'           => [
                    'model'       => $response->model,
                    'tokens_used' => $response->totalTokens(),
                    'cost_usd'    => $response->costUsd,
                    'latency_ms'  => $response->latencyMs,
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->logError($userId, 'proposal', 'project', $project->id, $e->getMessage());

            return response()->json([
                'message'  => 'AI service unavailable. Please try again later.',
                'fallback' => true,
            ], 503);
        }
    }

    public function summarize(Request $request): JsonResponse
    {
        if (! config('ai.enabled')) {
            return response()->json(['message' => 'AI not configured', 'fallback' => true], 503);
        }

        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
        ]);

        $client = Client::where('id', $validated['client_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $userId = $request->user()->id;

        try {
            $prompt = $this->prompts->get('summary');
            $ctx    = $this->buildCachedContext('client', $client->id, $userId, fn () => $this->context->forClient($client));

            $aiRequest = new AIRequest(
                taskType:     'summary',
                model:        config('ai.default_model.summary'),
                systemPrompt: $prompt->system_message,
                userPrompt:   $this->prompts->render($prompt, $ctx),
                temperature:  0.5,
                maxTokens:    800,
            );

            $response = $this->router->complete($aiRequest);

            $this->logger->logSuccess($userId, 'summary', 'client', $client->id, $prompt, $response);

            $client->update([
                'ai_summary'    => $response->content,
                'ai_summary_at' => now(),
            ]);

            return response()->json([
                'summary' => $response->content,
                'meta'    => [
                    'model'       => $response->model,
                    'tokens_used' => $response->totalTokens(),
                    'cost_usd'    => $response->costUsd,
                    'latency_ms'  => $response->latencyMs,
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->logError($userId, 'summary', 'client', $client->id, $e->getMessage());

            return response()->json([
                'message'  => 'AI service unavailable. Please try again later.',
                'fallback' => true,
            ], 503);
        }
    }

    public function usage(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $period = now()->format('Y-m');
        $start  = now()->startOfMonth();
        $end    = now()->endOfMonth();

        $logs = AiLog::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('task_type, COUNT(*) as calls, SUM(cost_usd) as cost_usd, SUM(total_tokens) as tokens')
            ->groupBy('task_type')
            ->get();

        $totalCalls  = $logs->sum('calls');
        $totalCost   = round($logs->sum('cost_usd'), 6);
        $totalTokens = $logs->sum('tokens');

        $byFeature = $logs->keyBy('task_type')->map(fn ($r) => [
            'calls'    => (int) $r->calls,
            'cost_usd' => round((float) $r->cost_usd, 6),
        ]);

        $daily = AiLog::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as calls, SUM(cost_usd) as cost_usd')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date'     => $r->date,
                'calls'    => (int) $r->calls,
                'cost_usd' => round((float) $r->cost_usd, 6),
            ]);

        return response()->json([
            'period'        => $period,
            'total_calls'   => (int) $totalCalls,
            'total_tokens'  => (int) $totalTokens,
            'total_cost_usd'=> $totalCost,
            'by_feature'    => $byFeature,
            'daily'         => $daily,
        ]);
    }

    private function buildCachedContext(string $type, int $id, int $userId, callable $builder): array
    {
        $key = "ai_ctx_{$type}_{$id}_{$userId}";

        return Cache::remember($key, 300, $builder);
    }

    private function parseJsonOutput(string $content): mixed
    {
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $content);
        $cleaned = preg_replace('/\s*```$/m', '', $cleaned);
        $cleaned = trim($cleaned ?? $content);

        $decoded = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['raw' => $content, 'parse_error' => true];
        }

        return $decoded['tasks'] ?? $decoded;
    }
}
