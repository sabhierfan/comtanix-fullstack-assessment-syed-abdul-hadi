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
 * Proxies the audit trail from the Node service so the browser never holds an
 * audit-service credential and the usual Sanctum auth + policies apply.
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

        // Resolve actor names in one query so the timeline can show who did what.
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
