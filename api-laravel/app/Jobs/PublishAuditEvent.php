<?php

namespace App\Jobs;

use App\Services\Audit\AuditServiceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one audit event to the Node audit service.
 *
 * The eventId is fixed when the job is created, so every retry sends the
 * same id and the audit service stores the event only once.
 */
class PublishAuditEvent implements ShouldQueue
{
    use Queueable;

    /** Attempts before the job lands in failed_jobs (about 1 hour of retrying in total). */
    public int $tries = 8;

    /**
     * @param  array{eventId: string, type: string, quoteRequestId: int, actorId: int|null, data: array<string, mixed>, occurredAt: string}  $event
     */
    public function __construct(public readonly array $event) {}

    /**
     * Exponential backoff in seconds between attempts.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300, 600, 1200];
    }

    public function handle(AuditServiceClient $client): void
    {
        try {
            $client->send($this->event);
        } catch (RequestException $e) {
            // A 4xx (other than 408/429) means the event itself is rejected, e.g. a bad
            // signature or invalid payload. Retrying will not help, so fail right away.
            $status = $e->response->status();
            if ($status >= 400 && $status < 500 && ! in_array($status, [408, 429], true)) {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Audit event could not be delivered', [
            'eventId' => $this->event['eventId'],
            'type' => $this->event['type'],
            'quoteRequestId' => $this->event['quoteRequestId'],
            'error' => $exception?->getMessage(),
        ]);
    }
}
