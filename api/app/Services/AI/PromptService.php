<?php

namespace App\Services\AI;

use App\Models\AiPrompt;
use RuntimeException;

class PromptService
{
    public function get(string $taskType): AiPrompt
    {
        $prompt = AiPrompt::where('task_type', $taskType)->where('is_active', true)->first();

        if ($prompt === null) {
            throw new RuntimeException("No active prompt found for task type: {$taskType}");
        }

        return $prompt;
    }

    public function render(AiPrompt $prompt, array $context): string
    {
        $template = $prompt->user_template;

        foreach ($context as $key => $value) {
            $template = str_replace("{{ {$key} }}", (string) $value, $template);
            $template = str_replace("{{{$key}}}", (string) $value, $template);
        }

        return $template;
    }
}
