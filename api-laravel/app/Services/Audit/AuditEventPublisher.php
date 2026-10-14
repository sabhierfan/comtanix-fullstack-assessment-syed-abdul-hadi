<?php

namespace App\Services\Audit;

use App\Enums\AuditEventType;
use App\Jobs\PublishAuditEvent;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Builds an audit event and puts it in the queue.
 *
 * IMPORTANT: call this INSIDE the same database transaction as the change.
 * We use the "database" queue, so the job is just a row in the jobs table.
 * That row is saved together with the change:
 *   - change saved   -> job saved too   (event is never lost)
 *   - change failed  -> job removed too (no event for something that didn't happen)
 * This pattern is called a "transactional outbox".
 */
class AuditEventPublisher
{
    /**
     * @param  array<string, mixed>  $data  extra details, e.g. ['from' => 'new', 'to' => 'in_review']
     */
    public function publish(AuditEventType $type, QuoteRequest $quoteRequest, ?User $actor, array $data = []): void
    {
        PublishAuditEvent::dispatch([
            // A new random id. Fixed from now on, so retries reuse it (no duplicates).
            'eventId' => (string) Str::uuid(),
            'type' => $type->value,
            'quoteRequestId' => $quoteRequest->id,
            // Who did it. null = a guest using the public form.
            'actorId' => $actor?->id,
            'data' => $data,
            // When it happened (not when it was delivered), so the timeline order is right.
            'occurredAt' => now()->toIso8601ZuluString('millisecond'),
        ]);
    }
}
