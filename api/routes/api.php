<?php

use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\KnowledgeController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::apiResource('clients', ClientController::class);
    Route::apiResource('clients.contacts', ContactController::class)->shallow();

    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('projects.tasks', TaskController::class)->shallow();

    // Proposals
    Route::apiResource('proposals', ProposalController::class);
    Route::post('proposals/{proposal}/convert-to-invoice', [ProposalController::class, 'convertToInvoice']);

    // Invoices
    Route::apiResource('invoices', InvoiceController::class);
    Route::put('invoices/{invoice}/items',      [InvoiceController::class, 'updateItems']);
    Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent']);
    Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);

    // Expenses
    Route::apiResource('expenses', ExpenseController::class);

    // Knowledge base
    Route::apiResource('knowledge', KnowledgeController::class);
    Route::post('knowledge/{knowledge}/toggle-pin', [KnowledgeController::class, 'togglePin']);

    // Finance summary
    Route::get('finance/summary', [FinanceController::class, 'summary']);

    Route::prefix('ai')->middleware('throttle:20,1')->group(function () {
        Route::post('break-tasks',        [AIController::class, 'breakTasks']);
        Route::post('generate-proposal',  [AIController::class, 'generateProposal']);
        Route::post('summarize',          [AIController::class, 'summarize']);
        Route::get('usage',               [AIController::class, 'usage']);
    });
});
