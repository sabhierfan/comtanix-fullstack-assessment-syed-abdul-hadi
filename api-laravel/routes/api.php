<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicQuoteRequestController;
use App\Http\Controllers\QuoteRequestAuditController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
| All URLs of the API. Laravel adds the "/api" prefix automatically,
| so '/login' below is really POST /api/login.
|
| "throttle:xxx" = rate limit defined in AppServiceProvider.
| "auth:sanctum"  = a valid login token is required (otherwise 401).
| {quoteRequest}  = Laravel loads that QuoteRequest by id for us (404 if missing or deleted).
*/

// ---- Public (no login needed) ----
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::post('/public/quote-requests', [PublicQuoteRequestController::class, 'store'])
    ->middleware('throttle:public-quotes');

// ---- Staff and Admin (login needed) ----
// Who may do what (e.g. only admin can assign) is decided in QuoteRequestPolicy.
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
