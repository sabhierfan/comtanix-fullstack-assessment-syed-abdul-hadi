<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequestRequest;
use App\Services\QuoteRequestWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PublicQuoteRequestController extends Controller
{
    public function store(StoreQuoteRequestRequest $request, QuoteRequestWorkflow $workflow): JsonResponse
    {
        // Bots get the same response as real users, so they cannot tell they were filtered.
        if ($request->isSpam()) {
            Log::notice('Quote request rejected by honeypot', ['ip' => $request->ip()]);
        } else {
            $workflow->submit($request->quoteAttributes(), $request->ip());
        }

        return response()->json([
            'message' => 'Thank you. Your quote request has been received.',
        ], 201);
    }
}
