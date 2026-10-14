<?php

namespace App\Jobs;

use App\Services\Audit\AuditServiceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A "letter" for the audit service, waiting in the queue (the jobs table).
 *
 * The API request does NOT send it. A separate process, `php artisan queue:work`,
 * picks it up and calls handle(). So if the audit service is slow or down,
 * the user of the main API never notices.
 *
 * The eventId is chosen when the job is created and never changes. If we send the
 * same event twice (a retry), the audit service sees the same eventId and stores it once.
 */
class PublishAuditEvent implements ShouldQueue
{
    use Queueable;

    /** Try up to 8 times. After that the job goes to the failed_jobs table. */
    public int $tries = 8;

    /**
     * @param  array{eventId: string, type: string, quoteRequestId: int, actorId: int|null, data: array<string, mixed>, occurredAt: string}  $event
     */
    public function __construct(public readonly array $event) {}

    /**
     * How long to wait before each retry, in seconds: 10s, 30s, 1m, 2m, 5m, 10m, 20m.
     * The wait grows each time ("exponential backoff") so we don't hammer a service
     * that is down. In total we keep trying for about 38 minutes.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300, 600, 1200];
    }

    /** The queue worker calls this. Laravel passes in the AuditServiceClient for us. */
    public function handle(AuditServiceClient $client): void
    {
        try {
            $client->send($this->event);
        } catch (RequestException $e) {
            // The audit service answered with an error code.
            // 4xx (except 408 timeout / 429 too many requests) means "your event is bad",
            // e.g. wrong signature. Sending it again won't fix that, so stop now.
            $status = $e->response->status();
            if ($status >= 400 && $status < 500 && ! in_array($status, [408, 429], true)) {
                $this->fail($e);

                return;
            }

            // 5xx = their problem, maybe temporary. Throw so the queue retries later.
            throw $e;
        }
        // Network errors (service down) also throw, and the queue retries them too.
    }

    /** Called once when the job has failed for good. We log it so someone can look. */
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
