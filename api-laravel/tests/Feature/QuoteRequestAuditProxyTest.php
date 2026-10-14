<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteRequestAuditProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_trail_is_proxied_with_the_read_key_and_actor_names(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $quote = QuoteRequest::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        Http::fake(['audit.test/events*' => Http::response([
            'data' => [
                ['eventId' => 'e2', 'type' => 'quote_request.assigned', 'quoteRequestId' => $quote->id, 'actorId' => $admin->id, 'data' => ['from' => null, 'to' => 5], 'occurredAt' => '2026-10-14T12:00:00.000Z'],
                ['eventId' => 'e1', 'type' => 'quote_request.created', 'quoteRequestId' => $quote->id, 'actorId' => null, 'data' => [], 'occurredAt' => '2026-10-14T11:00:00.000Z'],
            ],
            'meta' => ['page' => 1, 'perPage' => 20, 'total' => 2, 'lastPage' => 1],
        ])]);

        $this->getJson("/api/quote-requests/{$quote->id}/audit-events")
            ->assertOk()
            ->assertJsonPath('data.0.actor', ['id' => $admin->id, 'name' => 'Ada Admin'])
            ->assertJsonPath('data.1.actor', null)
            ->assertJsonPath('meta.total', 2);

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Api-Key', 'test-read-key')
            && str_contains($request->url(), 'quoteRequestId='.$quote->id));
    }

    public function test_audit_service_outage_returns_a_clear_503(): void
    {
        $quote = QuoteRequest::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        Http::fake(['audit.test/*' => Http::response('down', 503)]);

        $this->getJson("/api/quote-requests/{$quote->id}/audit-events")
            ->assertStatus(503)
            ->assertJsonPath('message', 'The audit trail is temporarily unavailable. Please try again shortly.');
    }
}
