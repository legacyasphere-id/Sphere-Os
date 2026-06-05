<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request, Client $client): JsonResponse
    {
        abort_if($client->user_id !== $request->user()->id, 403);

        return response()->json($client->contacts()->orderBy('name')->get());
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        abort_if($client->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role'  => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $contact = $client->contacts()->create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($contact, 201);
    }

    public function show(Request $request, Client $client, Contact $contact): JsonResponse
    {
        abort_if($client->user_id !== $request->user()->id || $contact->client_id !== $client->id, 403);

        return response()->json($contact);
    }

    public function update(Request $request, Client $client, Contact $contact): JsonResponse
    {
        abort_if($client->user_id !== $request->user()->id || $contact->client_id !== $client->id, 403);

        $data = $request->validate([
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role'  => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $contact->update($data);

        return response()->json($contact);
    }

    public function destroy(Request $request, Client $client, Contact $contact): JsonResponse
    {
        abort_if($client->user_id !== $request->user()->id || $contact->client_id !== $client->id, 403);

        $contact->delete();

        return response()->json(null, 204);
    }
}
