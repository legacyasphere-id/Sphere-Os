<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::where('user_id', $request->user()->id)
            ->with(['client:id,name', 'project:id,name']);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('date_from')) {
            $query->where('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('expense_date', '<=', $request->date_to);
        }

        $totalAmount = (clone $query)->sum('amount');
        $paginated   = $query->orderByDesc('expense_date')->paginate(20);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => array_merge($paginated->toArray(), ['total_amount' => (float) $totalAmount]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'category'     => ['required', 'string', 'max:100'],
            'expense_date' => ['required', 'date'],
            'client_id'    => ['nullable', 'integer', 'exists:clients,id'],
            'project_id'   => ['nullable', 'integer', 'exists:projects,id'],
            'notes'        => ['nullable', 'string'],
        ]);

        $expense = Expense::create([...$validated, 'user_id' => $request->user()->id]);

        return response()->json($expense->load(['client:id,name', 'project:id,name']), 201);
    }

    public function show(Request $request, Expense $expense): JsonResponse
    {
        abort_if($expense->user_id !== $request->user()->id, 403);

        return response()->json($expense->load(['client:id,name', 'project:id,name']));
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        abort_if($expense->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'title'        => ['sometimes', 'string', 'max:255'],
            'amount'       => ['sometimes', 'numeric', 'min:0'],
            'category'     => ['sometimes', 'string', 'max:100'],
            'expense_date' => ['sometimes', 'date'],
            'client_id'    => ['nullable', 'integer', 'exists:clients,id'],
            'project_id'   => ['nullable', 'integer', 'exists:projects,id'],
            'notes'        => ['nullable', 'string'],
        ]);

        $expense->update($validated);

        return response()->json($expense->load(['client:id,name', 'project:id,name']));
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        abort_if($expense->user_id !== $request->user()->id, 403);
        $expense->delete();

        return response()->json(null, 204);
    }
}
