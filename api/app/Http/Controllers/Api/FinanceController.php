<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $monthly = $this->monthlySummary($userId);

        $outstanding = Invoice::where('user_id', $userId)
            ->whereIn('status', ['sent', 'overdue'])
            ->selectRaw('COUNT(*) as count, SUM(total) as total')
            ->first();

        $paidYtd = Invoice::where('user_id', $userId)
            ->where('status', 'paid')
            ->whereYear('paid_at', now()->year)
            ->sum('total');

        $expenseByCategory = Expense::where('user_id', $userId)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $topClients = Invoice::where('invoices.user_id', $userId)
            ->where('invoices.status', 'paid')
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->selectRaw('clients.id, clients.name, SUM(invoices.total) as total')
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return response()->json([
            'monthly'            => $monthly,
            'outstanding_count'  => (int) $outstanding->count,
            'outstanding_total'  => (float) $outstanding->total,
            'paid_ytd'           => (float) $paidYtd,
            'expense_breakdown'  => $expenseByCategory,
            'top_clients'        => $topClients,
        ]);
    }

    private function monthlySummary(int $userId): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i));

        $isPgsql   = DB::connection()->getDriverName() === 'pgsql';
        $dateFmtPaid    = $isPgsql ? "to_char(paid_at, 'YYYY-MM')"     : "DATE_FORMAT(paid_at, '%Y-%m')";
        $dateFmtExpense = $isPgsql ? "to_char(expense_date, 'YYYY-MM')" : "DATE_FORMAT(expense_date, '%Y-%m')";

        $paidRevenue = Invoice::where('user_id', $userId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("{$dateFmtPaid} as month, SUM(total) as revenue")
            ->groupByRaw("{$dateFmtPaid}")
            ->pluck('revenue', 'month');

        $expenses = Expense::where('user_id', $userId)
            ->where('expense_date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("{$dateFmtExpense} as month, SUM(amount) as expenses")
            ->groupByRaw("{$dateFmtExpense}")
            ->pluck('expenses', 'month');

        return $months->map(function ($date) use ($paidRevenue, $expenses) {
            $key      = $date->format('Y-m');
            $revenue  = (float) ($paidRevenue[$key] ?? 0);
            $expense  = (float) ($expenses[$key] ?? 0);

            return [
                'month'    => $key,
                'label'    => $date->format('M Y'),
                'revenue'  => $revenue,
                'expenses' => $expense,
                'profit'   => round($revenue - $expense, 2),
            ];
        })->values()->all();
    }
}
