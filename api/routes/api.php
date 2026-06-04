<?php

use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProjectController;
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

    Route::prefix('ai')->middleware('throttle:20,1')->group(function () {
        Route::post('break-tasks',        [AIController::class, 'breakTasks']);
        Route::post('generate-proposal',  [AIController::class, 'generateProposal']);
        Route::post('summarize',          [AIController::class, 'summarize']);
        Route::get('usage',               [AIController::class, 'usage']);
    });
});
