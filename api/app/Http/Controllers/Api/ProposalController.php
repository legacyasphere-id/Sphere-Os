<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Proposal::where('user_id', $request->user()->id)
            ->with(['client:id,name', 'project:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        return response()->json(
            $query->orderByDesc('updated_at')->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'client_id'  => ['nullable', 'integer', 'exists:clients,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'content'    => ['nullable', 'string'],
            'status'     => ['nullable', 'in:draft,sent,accepted,rejected,converted'],
            'notes'      => ['nullable', 'string'],
            'ai_generated' => ['nullable', 'boolean'],
        ]);

        $proposal = Proposal::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status'  => $validated['status'] ?? 'draft',
        ]);

        return response()->json($proposal->load(['client:id,name', 'project:id,name']), 201);
    }

    public function show(Request $request, Proposal $proposal): JsonResponse
    {
        abort_if($proposal->user_id !== $request->user()->id, 403);

        return response()->json(
            $proposal->load(['client:id,name,company', 'project:id,name', 'invoice:id,invoice_number,status,total'])
        );
    }

    public function update(Request $request, Proposal $proposal): JsonResponse
    {
        abort_if($proposal->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'title'        => ['sometimes', 'string', 'max:255'],
            'client_id'    => ['nullable', 'integer', 'exists:clients,id'],
            'project_id'   => ['nullable', 'integer', 'exists:projects,id'],
            'content'      => ['nullable', 'string'],
            'status'       => ['nullable', 'in:draft,sent,accepted,rejected,converted'],
            'notes'        => ['nullable', 'string'],
            'sent_at'      => ['nullable', 'date'],
            'responded_at' => ['nullable', 'date'],
        ]);

        $proposal->update($validated);

        return response()->json($proposal->load(['client:id,name', 'project:id,name']));
    }

    public function destroy(Request $request, Proposal $proposal): JsonResponse
    {
        abort_if($proposal->user_id !== $request->user()->id, 403);
        $proposal->delete();

        return response()->json(null, 204);
    }

    public function convertToInvoice(Request $request, Proposal $proposal): JsonResponse
    {
        abort_if($proposal->user_id !== $request->user()->id, 403);

        if ($proposal->invoice()->exists()) {
            return response()->json(['message' => 'This proposal has already been converted to an invoice.'], 409);
        }

        $invoice = Invoice::create([
            'user_id'        => $request->user()->id,
            'client_id'      => $proposal->client_id,
            'project_id'     => $proposal->project_id,
            'proposal_id'    => $proposal->id,
            'invoice_number' => Invoice::nextNumberForUser($request->user()->id),
            'status'         => 'draft',
            'issue_date'     => today(),
            'currency'       => config('app.currency', 'USD'),
        ]);

        $proposal->update(['status' => 'converted']);

        return response()->json($invoice->load(['client:id,name', 'project:id,name', 'proposal:id,title']), 201);
    }
}
