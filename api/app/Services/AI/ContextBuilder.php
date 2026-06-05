<?php

namespace App\Services\AI;

use App\Models\Client;
use App\Models\Project;

class ContextBuilder
{
    private const PII_FIELDS = ['email', 'phone'];
    private const CHARS_PER_TOKEN = 4;

    public function forProject(Project $project): array
    {
        $project->loadMissing(['client', 'tasks']);

        $existingTasks = $project->tasks
            ->map(fn ($t) => "- [{$t->status}] {$t->title}")
            ->join("\n");

        $context = [
            'project_name'        => $project->name,
            'project_description' => $project->description ?? 'No description provided.',
            'project_status'      => $project->status,
            'project_due_date'    => $project->due_date?->format('Y-m-d') ?? 'No deadline set',
            'client_name'         => $project->client?->name ?? 'No client',
            'client_company'      => $project->client?->company ?? '',
            'existing_tasks'      => $existingTasks ?: 'None yet',
            'existing_task_count' => $project->tasks->count(),
        ];

        return $this->trimToTokenBudget($context);
    }

    public function forClient(Client $client): array
    {
        $client->loadMissing(['projects', 'contacts']);

        $projectSummary = $client->projects
            ->map(fn ($p) => "- {$p->name} [{$p->status}]")
            ->join("\n");

        $context = [
            'client_name'     => $client->name,
            'client_company'  => $client->company ?? '',
            'client_status'   => $client->status,
            'client_notes'    => $client->notes ?? 'No notes.',
            'project_count'   => $client->projects->count(),
            'projects'        => $projectSummary ?: 'No projects',
            'contact_count'   => $client->contacts->count(),
        ];

        return $this->trimToTokenBudget($context);
    }

    private function trimToTokenBudget(array $context): array
    {
        $maxChars = config('ai.limits.max_context_tokens', 3000) * self::CHARS_PER_TOKEN;

        $total = array_sum(array_map('strlen', $context));

        if ($total <= $maxChars) {
            return $context;
        }

        // Trim long text fields first
        foreach (['client_notes', 'project_description', 'existing_tasks', 'projects'] as $field) {
            if (isset($context[$field]) && strlen($context[$field]) > 500) {
                $context[$field] = substr($context[$field], 0, 500) . '...';
            }

            if (array_sum(array_map('strlen', $context)) <= $maxChars) {
                break;
            }
        }

        return $context;
    }
}
