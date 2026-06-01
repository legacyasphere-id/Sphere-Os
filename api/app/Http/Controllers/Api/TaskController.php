<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $tasks = $project->tasks()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("FIELD(priority,'high','medium','low')")
            ->orderBy('due_date')
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'in:todo,in_progress,done'],
            'priority'    => ['nullable', 'in:low,medium,high'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $task = $project->tasks()->create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($task, 201);
    }

    public function show(Request $request, Project $project, Task $task): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id || $task->project_id !== $project->id, 403);

        return response()->json($task);
    }

    public function update(Request $request, Project $project, Task $task): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id || $task->project_id !== $project->id, 403);

        $data = $request->validate([
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'in:todo,in_progress,done'],
            'priority'    => ['nullable', 'in:low,medium,high'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $task->update($data);

        return response()->json($task);
    }

    public function destroy(Request $request, Project $project, Task $task): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id || $task->project_id !== $project->id, 403);

        $task->delete();

        return response()->json(null, 204);
    }
}
