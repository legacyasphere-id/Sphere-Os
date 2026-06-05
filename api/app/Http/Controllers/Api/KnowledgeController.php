<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KnowledgeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KnowledgeDocument::where('user_id', $request->user()->id)
            ->with(['client:id,name', 'project:id,name']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('pinned')) {
            $query->where('is_pinned', (bool) $request->pinned);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $hasFulltext = $this->hasFulltextIndex();

            if ($hasFulltext) {
                $query->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$search . '*']);
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                      ->orWhere('content', 'like', '%' . $search . '%');
                });
            }
        }

        $query->orderByDesc('is_pinned')->orderByDesc('updated_at');

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'content'    => ['nullable', 'string'],
            'type'       => ['nullable', 'in:note,template,sop,reference'],
            'tags'       => ['nullable', 'array'],
            'tags.*'     => ['string', 'max:50'],
            'is_pinned'  => ['nullable', 'boolean'],
            'client_id'  => ['nullable', 'integer', 'exists:clients,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $doc = KnowledgeDocument::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'type'    => $validated['type'] ?? 'note',
        ]);

        return response()->json($doc->load(['client:id,name', 'project:id,name']), 201);
    }

    public function show(Request $request, KnowledgeDocument $knowledge): JsonResponse
    {
        abort_if($knowledge->user_id !== $request->user()->id, 403);

        return response()->json($knowledge->load(['client:id,name', 'project:id,name']));
    }

    public function update(Request $request, KnowledgeDocument $knowledge): JsonResponse
    {
        abort_if($knowledge->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'title'      => ['sometimes', 'string', 'max:255'],
            'content'    => ['nullable', 'string'],
            'type'       => ['nullable', 'in:note,template,sop,reference'],
            'tags'       => ['nullable', 'array'],
            'tags.*'     => ['string', 'max:50'],
            'is_pinned'  => ['nullable', 'boolean'],
            'client_id'  => ['nullable', 'integer', 'exists:clients,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $knowledge->update($validated);

        return response()->json($knowledge->load(['client:id,name', 'project:id,name']));
    }

    public function destroy(Request $request, KnowledgeDocument $knowledge): JsonResponse
    {
        abort_if($knowledge->user_id !== $request->user()->id, 403);
        $knowledge->delete();

        return response()->json(null, 204);
    }

    public function togglePin(Request $request, KnowledgeDocument $knowledge): JsonResponse
    {
        abort_if($knowledge->user_id !== $request->user()->id, 403);
        $knowledge->update(['is_pinned' => ! $knowledge->is_pinned]);

        return response()->json($knowledge);
    }

    private function hasFulltextIndex(): bool
    {
        $results = DB::select(
            "SHOW INDEX FROM knowledge_documents WHERE Key_name = 'ft_title_content'"
        );

        return count($results) > 0;
    }
}
