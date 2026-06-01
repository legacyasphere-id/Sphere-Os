<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::where('user_id', $request->user()->id)
            ->with('client:id,name,company')
            ->withCount(['tasks', 'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'done')]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $projects = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'client_id'   => ['nullable', 'exists:clients,id'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'in:active,completed,archived'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $project = Project::create(array_merge($data, ['user_id' => $request->user()->id]));
        $project->load('client:id,name,company');

        return response()->json($project, 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $project->load(['client:id,name,company', 'tasks' => fn ($q) => $q->orderBy('due_date')->orderByDesc('priority')]);

        return response()->json($project);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'client_id'   => ['nullable', 'exists:clients,id'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'in:active,completed,archived'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $project->update($data);
        $project->load('client:id,name,company');

        return response()->json($project);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $project->delete();

        return response()->json(null, 204);
    }
}
