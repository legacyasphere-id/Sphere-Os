<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $today  = Carbon::today();

        $clientCount        = Client::where('user_id', $userId)->count();
        $activeProjectCount = Project::where('user_id', $userId)->where('status', 'active')->count();
        $openTaskCount      = Task::where('user_id', $userId)->whereIn('status', ['todo', 'in_progress'])->count();
        $overdueTaskCount   = Task::where('user_id', $userId)
            ->whereIn('status', ['todo', 'in_progress'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->count();

        $upcomingTasks = Task::where('user_id', $userId)
            ->whereIn('status', ['todo', 'in_progress'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])
            ->with('project:id,name')
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'title', 'priority', 'due_date', 'project_id']);

        $upcomingActions = Client::where('user_id', $userId)
            ->whereNotNull('next_action_date')
            ->where('next_action_date', '>=', $today)
            ->orderBy('next_action_date')
            ->limit(5)
            ->get(['id', 'name', 'company', 'next_action_date', 'next_action_note']);

        $recentClients = Client::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'company', 'status', 'created_at']);

        $outstandingInvoices = Invoice::where('user_id', $userId)
            ->whereIn('status', ['sent', 'overdue'])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        $paidThisMonth = Invoice::where('user_id', $userId)
            ->where('status', 'paid')
            ->whereMonth('paid_at', $today->month)
            ->whereYear('paid_at', $today->year)
            ->sum('total');

        $expensesThisMonth = Expense::where('user_id', $userId)
            ->whereMonth('expense_date', $today->month)
            ->whereYear('expense_date', $today->year)
            ->sum('amount');

        return response()->json([
            'stats' => [
                'clients'         => $clientCount,
                'active_projects' => $activeProjectCount,
                'open_tasks'      => $openTaskCount,
                'overdue_tasks'   => $overdueTaskCount,
            ],
            'upcoming_tasks'   => $upcomingTasks,
            'upcoming_actions' => $upcomingActions,
            'recent_clients'   => $recentClients,
            'finance_snapshot' => [
                'outstanding_count' => (int) $outstandingInvoices->count,
                'outstanding_total' => (float) $outstandingInvoices->total,
                'paid_this_month'   => (float) $paidThisMonth,
                'expenses_this_month' => (float) $expensesThisMonth,
            ],
        ]);
    }
}
