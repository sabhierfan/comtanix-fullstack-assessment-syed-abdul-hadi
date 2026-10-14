<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Jobs\PublishAuditEvent;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->staff = User::factory()->create();
        Sanctum::actingAs($this->staff);
    }

    public function test_invalid_transition_returns_a_clear_error_and_changes_nothing(): void
    {
        $quote = QuoteRequest::factory()->assignedTo($this->staff)->create();

        $this->patchJson("/api/quote-requests/{$quote->id}/status", ['status' => 'quoted'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot change status from "new" to "quoted".')
            ->assertJsonPath('current_status', 'new')
            ->assertJsonPath('allowed_transitions', ['in_review'])
            ->assertJsonValidationErrors('status');

        $this->assertSame(QuoteStatus::New, $quote->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_status_change_dispatches_a_signed_event_job(): void
    {
        $quote = QuoteRequest::factory()->assignedTo($this->staff)->create();

        $this->patchJson("/api/quote-requests/{$quote->id}/status", ['status' => 'in_review'])
            ->assertOk()
            ->assertJsonPath('status', 'in_review')
            ->assertJsonPath('allowed_transitions', ['quoted', 'closed']);

        Queue::assertPushed(PublishAuditEvent::class, 1);
        Queue::assertPushed(PublishAuditEvent::class, fn (PublishAuditEvent $job) => $job->event['type'] === 'quote_request.status_changed'
            && $job->event['quoteRequestId'] === $quote->id
            && $job->event['actorId'] === $this->staff->id
            && $job->event['data'] === ['from' => 'new', 'to' => 'in_review']);
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function transitions(): array
    {
        return [
            'new to in_review' => ['new', 'in_review', 200],
            'in_review to quoted' => ['in_review', 'quoted', 200],
            'in_review to closed' => ['in_review', 'closed', 200],
            'quoted to closed' => ['quoted', 'closed', 200],
            'new to closed' => ['new', 'closed', 422],
            'quoted back to in_review' => ['quoted', 'in_review', 422],
            'closed is final' => ['closed', 'new', 422],
            'same status' => ['in_review', 'in_review', 422],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transition_rules(string $from, string $to, int $expected): void
    {
        $quote = QuoteRequest::factory()->assignedTo($this->staff)->status(QuoteStatus::from($from))->create();

        $this->patchJson("/api/quote-requests/{$quote->id}/status", ['status' => $to])
            ->assertStatus($expected);
    }

    public function test_unknown_status_is_a_validation_error(): void
    {
        $quote = QuoteRequest::factory()->assignedTo($this->staff)->create();

        $this->patchJson("/api/quote-requests/{$quote->id}/status", ['status' => 'archived'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
