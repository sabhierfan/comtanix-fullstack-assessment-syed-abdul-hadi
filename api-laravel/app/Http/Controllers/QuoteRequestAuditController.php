<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Audit\AuditServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * GET /api/quote-requests/{id}/audit-events: the history shown in the dashboard's timeline.
 *
 * This is a "proxy": the browser asks Laravel, and Laravel asks the Node audit service.
 * Why not let the browser call Node directly?
 *   - The browser never sees the audit service's secret key.
 *   - The same login and permission checks as the rest of the API apply.
 *   - Laravel can add the actor's name (Node only knows user ids).
 *   - If Node is down, we return a clean 503 and the rest of the page still works.
 */
class QuoteRequestAuditController extends Controller
{
    public function index(Request $request, QuoteRequest $quoteRequest, AuditServiceClient $audit): JsonResponse
    {
        Gate::authorize('view', $quoteRequest);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $result = $audit->listEvents(
                $quoteRequest->id,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Audit trail unavailable', ['quoteRequestId' => $quoteRequest->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'The audit trail is temporarily unavailable. Please try again shortly.'], 503);
        }

        // Turn actor ids into names with ONE query (not one query per event).
        $actorIds = collect($result['data'])->pluck('actorId')->filter()->unique();
        $actors = User::whereIn('id', $actorIds)->get(['id', 'name'])->keyBy('id');

        return response()->json([
            'data' => collect($result['data'])->map(fn (array $event) => [
                'id' => $event['eventId'],
                'type' => $event['type'],
                'actor' => $event['actorId'] && $actors->has($event['actorId'])
                    ? ['id' => $event['actorId'], 'name' => $actors[$event['actorId']]->name]
                    : null,
                'data' => $event['data'],
                'occurred_at' => $event['occurredAt'],
            ])->values(),
            'meta' => $result['meta'],
        ]);
    }
}
