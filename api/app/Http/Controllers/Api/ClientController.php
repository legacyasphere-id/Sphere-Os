<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::where('user_id', $request->user()->id)
            ->with(['contacts:id,client_id,name,role', 'projects:id,client_id,name,status'])
            ->withCount('projects');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($clients);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'company'          => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'status'           => ['nullable', 'in:lead,prospect,client,lost'],
            'next_action_date' => ['nullable', 'date'],
            'next_action_note' => ['nullable', 'string', 'max:500'],
            'notes'            => ['nullable', 'string'],
        ]);

        $client = Client::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($client, 201);
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $this->authorize($request->user(), $client);

        $client->load(['contacts', 'projects.tasks']);

        return response()->json($client);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorize($request->user(), $client);

        $data = $request->validate([
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'company'          => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'status'           => ['nullable', 'in:lead,prospect,client,lost'],
            'next_action_date' => ['nullable', 'date'],
            'next_action_note' => ['nullable', 'string', 'max:500'],
            'notes'            => ['nullable', 'string'],
        ]);

        $client->update($data);

        return response()->json($client);
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->authorize($request->user(), $client);

        $client->delete();

        return response()->json(null, 204);
    }

    private function authorize($user, Client $client): void
    {
        abort_if($client->user_id !== $user->id, 403);
    }
}
