<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicQuoteRequestController;
use App\Http\Controllers\QuoteRequestAuditController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::post('/public/quote-requests', [PublicQuoteRequestController::class, 'store'])
    ->middleware('throttle:public-quotes');

// Staff (Admin + Staff)
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/users', [UserController::class, 'index']);

    Route::get('/quote-requests', [QuoteRequestController::class, 'index']);
    Route::get('/quote-requests/{quoteRequest}', [QuoteRequestController::class, 'show']);
    Route::patch('/quote-requests/{quoteRequest}/status', [QuoteRequestController::class, 'updateStatus']);
    Route::patch('/quote-requests/{quoteRequest}/assign', [QuoteRequestController::class, 'assign']);
    Route::delete('/quote-requests/{quoteRequest}', [QuoteRequestController::class, 'destroy']);
    Route::get('/quote-requests/{quoteRequest}/audit-events', [QuoteRequestAuditController::class, 'index']);
});
