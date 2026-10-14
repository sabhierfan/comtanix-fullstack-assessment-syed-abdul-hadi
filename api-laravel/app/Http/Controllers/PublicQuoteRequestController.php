<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequestRequest;
use App\Services\QuoteRequestWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/public/quote-requests: the public "request a quote" form. No login needed.
 *
 * Protection against abuse:
 *   - Rate limit: 5 per minute and 50 per day per IP (see routes/api.php + AppServiceProvider).
 *   - Validation: StoreQuoteRequestRequest checks every field.
 *   - Honeypot: a hidden "website" field. Humans leave it empty; bots fill it.
 */
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

        // We don't return the new id, so outsiders can't guess or count our requests.
        return response()->json([
            'message' => 'Thank you. Your quote request has been received.',
        ], 201);
    }
}
