<?php

namespace Tests\Feature;

use App\Jobs\PublishAuditEvent;
use App\Services\Audit\AuditServiceClient;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishAuditEventJobTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function event(): array
    {
        return [
            'eventId' => '6f1c2a4e-1d2b-4c3d-8e9f-0a1b2c3d4e5f',
            'type' => 'quote_request.status_changed',
            'quoteRequestId' => 42,
            'actorId' => 7,
            'data' => ['from' => 'new', 'to' => 'in_review'],
            'occurredAt' => '2026-10-14T12:00:00.000Z',
        ];
    }

    public function test_event_is_sent_with_a_valid_hmac_signature_over_the_raw_body(): void
    {
        Http::fake(['audit.test/*' => Http::response(['status' => 'stored'], 201)]);

        (new PublishAuditEvent($this->event()))->handle(app(AuditServiceClient::class));

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://audit.test/events'
                && $request->header('X-Signature')[0] === hash_hmac('sha256', $request->body(), 'test-secret')
                && $request['eventId'] === '6f1c2a4e-1d2b-4c3d-8e9f-0a1b2c3d4e5f';
        });
    }

    public function test_server_errors_are_rethrown_so_the_queue_retries_with_backoff(): void
    {
        Http::fake(['audit.test/*' => Http::response('down', 503)]);
        $job = new PublishAuditEvent($this->event());

        $this->assertSame([10, 30, 60, 120, 300, 600, 1200], $job->backoff());
        $this->expectException(RequestException::class);

        $job->handle(app(AuditServiceClient::class));
    }

    public function test_rejected_events_fail_immediately_without_retrying(): void
    {
        Http::fake(['audit.test/*' => Http::response(['error' => 'Invalid signature'], 401)]);

        $queueJob = $this->mock(Job::class);
        $queueJob->shouldReceive('fail')->once();

        $job = new PublishAuditEvent($this->event());
        $job->setJob($queueJob);

        $job->handle(app(AuditServiceClient::class));
    }
}
