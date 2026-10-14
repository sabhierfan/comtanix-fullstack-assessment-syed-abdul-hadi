<?php

namespace App\Services\Audit;

use App\Enums\AuditEventType;
use App\Jobs\PublishAuditEvent;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Support\Str;

class AuditEventPublisher
{
    /**
     * Queue an audit event. Call this inside the same database transaction as the
     * change it describes: with the database queue driver the job row is written
     * atomically with the change (a simple transactional outbox), so an event is
     * never lost or sent for a change that was rolled back. The HTTP call to the
     * audit service happens later in a queue worker, never in the API request.
     *
     * @param  array<string, mixed>  $data
     */
    public function publish(AuditEventType $type, QuoteRequest $quoteRequest, ?User $actor, array $data = []): void
    {
        PublishAuditEvent::dispatch([
            'eventId' => (string) Str::uuid(),
            'type' => $type->value,
            'quoteRequestId' => $quoteRequest->id,
            'actorId' => $actor?->id,
            'data' => $data,
            'occurredAt' => now()->toIso8601ZuluString('millisecond'),
        ]);
    }
}
