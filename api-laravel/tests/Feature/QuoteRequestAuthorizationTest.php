<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Jobs\PublishAuditEvent;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_staff_cannot_update_a_request_assigned_to_someone_else(): void
    {
        $staff = User::factory()->create();
        $quote = QuoteRequest::factory()->assignedTo(User::factory()->create())->create();

        Sanctum::actingAs($staff);

        $this->patchJson("/api/quote-requests/{$quote->id}/status", ['status' => 'in_review'])
            ->assertForbidden();

        $this->assertSame(QuoteStatus::New, $quote->fresh()->status);
    }

    public function test_staff_cannot_update_an_unassigned_request(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $quote = QuoteRequest::factory()->create();

        $this->patchJson("/api/quote-requests/{$quote->id}/status", ['status' => 'in_review'])
            ->assertForbidden();
    }

    public function test_staff_can_update_their_own_request(): void
    {
        $staff = User::factory()->create();
        $quote = QuoteRequest::factory()->assignedTo($staff)->create();

        Sanctum::actingAs($staff);

        $this->patchJson("/api/quote-requests/{$quote->id}/status", ['status' => 'in_review'])
            ->assertOk()
            ->assertJsonPath('status', 'in_review');
    }

    public function test_staff_cannot_assign_requests_not_even_to_themselves(): void
    {
        $staff = User::factory()->create();
        $quote = QuoteRequest::factory()->create();

        Sanctum::actingAs($staff);

        $this->patchJson("/api/quote-requests/{$quote->id}/assign", ['assigned_to' => $staff->id])
            ->assertForbidden();

        $this->assertNull($quote->fresh()->assigned_to);
        Queue::assertNothingPushed();
    }

    public function test_admin_can_assign_and_an_audit_event_is_queued(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();
        $quote = QuoteRequest::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/quote-requests/{$quote->id}/assign", ['assigned_to' => $staff->id])
            ->assertOk()
            ->assertJsonPath('assignee.id', $staff->id);

        Queue::assertPushed(PublishAuditEvent::class, fn (PublishAuditEvent $job) => $job->event['type'] === 'quote_request.assigned'
            && $job->event['actorId'] === $admin->id
            && $job->event['data'] === ['from' => null, 'to' => $staff->id]);
    }

    public function test_only_admin_can_soft_delete(): void
    {
        $staff = User::factory()->create();
        $quote = QuoteRequest::factory()->assignedTo($staff)->create();

        Sanctum::actingAs($staff);
        $this->deleteJson("/api/quote-requests/{$quote->id}")->assertForbidden();
        $this->assertNotSoftDeleted($quote);

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->deleteJson("/api/quote-requests/{$quote->id}")->assertNoContent();
        $this->assertSoftDeleted($quote);

        $this->getJson("/api/quote-requests/{$quote->id}")->assertNotFound();
        Queue::assertPushed(PublishAuditEvent::class, fn (PublishAuditEvent $job) => $job->event['type'] === 'quote_request.deleted');
    }

    public function test_resource_exposes_permissions_for_the_current_user(): void
    {
        $staff = User::factory()->create();
        $quote = QuoteRequest::factory()->assignedTo($staff)->create();

        Sanctum::actingAs($staff);

        $this->getJson("/api/quote-requests/{$quote->id}")
            ->assertOk()
            ->assertJsonPath('can', ['update_status' => true, 'assign' => false, 'delete' => false]);
    }
}
