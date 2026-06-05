<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::where('user_id', $request->user()->id)
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

        $paginated = $query->orderByDesc('issue_date')->paginate(20);

        $meta = [
            'outstanding_total' => Invoice::where('user_id', $request->user()->id)
                ->whereIn('status', ['sent', 'overdue'])
                ->sum('total'),
            'paid_this_month' => Invoice::where('user_id', $request->user()->id)
                ->where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('total'),
        ];

        return response()->json([
            'data'  => $paginated->items(),
            'meta'  => array_merge($paginated->toArray(), $meta),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id'               => ['nullable', 'integer', 'exists:clients,id'],
            'project_id'              => ['nullable', 'integer', 'exists:projects,id'],
            'proposal_id'             => ['nullable', 'integer', 'exists:proposals,id'],
            'issue_date'              => ['required', 'date'],
            'due_date'                => ['nullable', 'date', 'after_or_equal:issue_date'],
            'tax_rate'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'                   => ['nullable', 'string'],
            'currency'                => ['nullable', 'string', 'size:3'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.description'     => ['required', 'string', 'max:500'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.sort_order'      => ['nullable', 'integer'],
        ]);

        $invoice = DB::transaction(function () use ($validated, $request) {
            $taxRate = (float) ($validated['tax_rate'] ?? 0);

            $invoice = Invoice::create([
                'user_id'        => $request->user()->id,
                'client_id'      => $validated['client_id'] ?? null,
                'project_id'     => $validated['project_id'] ?? null,
                'proposal_id'    => $validated['proposal_id'] ?? null,
                'invoice_number' => Invoice::nextNumberForUser($request->user()->id),
                'status'         => 'draft',
                'issue_date'     => $validated['issue_date'],
                'due_date'       => $validated['due_date'] ?? null,
                'tax_rate'       => $taxRate,
                'currency'       => $validated['currency'] ?? config('app.currency', 'USD'),
                'notes'          => $validated['notes'] ?? null,
                'subtotal'       => 0,
                'tax_amount'     => 0,
                'total'          => 0,
            ]);

            $this->syncItems($invoice, $validated['items']);

            return $invoice;
        });

        return response()->json(
            $invoice->load(['client:id,name', 'project:id,name', 'items']),
            201
        );
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== $request->user()->id, 403);

        return response()->json(
            $invoice->load(['client:id,name,company', 'project:id,name', 'proposal:id,title', 'items'])
        );
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'client_id'  => ['nullable', 'integer', 'exists:clients,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'issue_date' => ['sometimes', 'date'],
            'due_date'   => ['nullable', 'date'],
            'tax_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'      => ['nullable', 'string'],
            'currency'   => ['nullable', 'string', 'size:3'],
        ]);

        DB::transaction(function () use ($invoice, $validated) {
            $invoice->update($validated);

            if (isset($validated['tax_rate'])) {
                $taxRate        = (float) $validated['tax_rate'];
                $taxAmount      = round($invoice->subtotal * $taxRate / 100, 2);
                $invoice->update([
                    'tax_amount' => $taxAmount,
                    'total'      => round($invoice->subtotal + $taxAmount, 2),
                ]);
            }
        });

        return response()->json($invoice->fresh()->load(['client:id,name', 'project:id,name', 'items']));
    }

    public function updateItems(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.description'     => ['required', 'string', 'max:500'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.sort_order'      => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($invoice, $validated) {
            $invoice->items()->delete();
            $this->syncItems($invoice, $validated['items']);
        });

        return response()->json($invoice->fresh()->load(['client:id,name', 'project:id,name', 'items']));
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== $request->user()->id, 403);

        if (! in_array($invoice->status, ['draft', 'cancelled'])) {
            return response()->json(['message' => 'Only draft or cancelled invoices can be deleted.'], 422);
        }

        $invoice->delete();

        return response()->json(null, 204);
    }

    public function markSent(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== $request->user()->id, 403);
        $invoice->update(['status' => 'sent']);

        return response()->json($invoice->fresh()->load(['client:id,name', 'items']));
    }

    public function markPaid(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== $request->user()->id, 403);
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        return response()->json($invoice->fresh()->load(['client:id,name', 'items']));
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        $subtotal = 0;

        foreach ($items as $i => $itemData) {
            $amount = round((float) $itemData['quantity'] * (float) $itemData['unit_price'], 2);
            $subtotal += $amount;

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $itemData['description'],
                'quantity'    => $itemData['quantity'],
                'unit_price'  => $itemData['unit_price'],
                'amount'      => $amount,
                'sort_order'  => $itemData['sort_order'] ?? $i,
            ]);
        }

        $taxRate   = (float) $invoice->tax_rate;
        $taxAmount = round($subtotal * $taxRate / 100, 2);

        $invoice->update([
            'subtotal'   => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'total'      => round($subtotal + $taxAmount, 2),
        ]);
    }
}
