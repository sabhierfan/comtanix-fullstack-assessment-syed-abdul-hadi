<?php

namespace Tests\Feature;

use App\Jobs\PublishAuditEvent;
use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicQuoteRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'client_name' => 'Jane Prospect',
            'email' => 'jane@example.com',
            'company' => 'Acme Ltd',
            'service_type' => 'web_development',
            'budget_range' => '15k_50k',
            'message' => 'We need a new customer portal built in Q1.',
            ...$overrides,
        ];
    }

    public function test_guest_can_submit_a_quote_request_and_an_audit_event_is_queued(): void
    {
        $this->postJson('/api/public/quote-requests', $this->validPayload())
            ->assertCreated()
            ->assertJsonMissingPath('id');

        $quote = QuoteRequest::sole();
        $this->assertSame('new', $quote->status->value);
        $this->assertNull($quote->assigned_to);

        Queue::assertPushed(PublishAuditEvent::class, fn (PublishAuditEvent $job) => $job->event['type'] === 'quote_request.created'
            && $job->event['quoteRequestId'] === $quote->id
            && $job->event['actorId'] === null
            && Str::isUuid($job->event['eventId']));
    }

    public function test_submission_is_validated(): void
    {
        $this->postJson('/api/public/quote-requests', [
            'client_name' => '',
            'email' => 'not-an-email',
            'service_type' => 'space_travel',
            'budget_range' => 'unlimited',
            'message' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_name', 'email', 'service_type', 'budget_range', 'message']);

        $this->assertDatabaseCount('quote_requests', 0);
        Queue::assertNothingPushed();
    }

    public function test_status_and_assignment_cannot_be_injected_by_the_public(): void
    {
        $this->postJson('/api/public/quote-requests', $this->validPayload(['status' => 'closed', 'assigned_to' => 1]))
            ->assertCreated();

        $quote = QuoteRequest::sole();
        $this->assertSame('new', $quote->status->value);
        $this->assertNull($quote->assigned_to);
    }

    public function test_honeypot_submissions_look_successful_but_are_not_stored(): void
    {
        $this->postJson('/api/public/quote-requests', $this->validPayload(['website' => 'http://spam.example']))
            ->assertCreated();

        $this->assertDatabaseCount('quote_requests', 0);
        Queue::assertNothingPushed();
    }

    public function test_public_endpoint_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/public/quote-requests', $this->validPayload())->assertCreated();
        }

        $this->postJson('/api/public/quote-requests', $this->validPayload())
            ->assertTooManyRequests();

        $this->assertDatabaseCount('quote_requests', 5);
    }
}
