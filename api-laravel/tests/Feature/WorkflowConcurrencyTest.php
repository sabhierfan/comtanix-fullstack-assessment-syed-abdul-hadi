<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Jobs\PublishAuditEvent;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\QuoteRequestWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The workflow checks permission again after locking the row.
 * These tests call the workflow with an object that is out of date,
 * which is what happens when two requests overlap.
 */
class WorkflowConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_staff_cannot_change_status_after_being_unassigned_in_the_meantime(): void
    {
        $staff = User::factory()->create();
        $quote = QuoteRequest::factory()->assignedTo($staff)->create();

        // Staff loaded the request while it was still theirs...
        $staleCopy = $quote->fresh();

        // ...then an admin reassigned it before the status change ran.
        $quote->forceFill(['assigned_to' => User::factory()->create()->id])->save();

        $this->expectException(AuthorizationException::class);

        try {
            app(QuoteRequestWorkflow::class)->changeStatus($staleCopy, QuoteStatus::InReview, $staff);
        } finally {
            $this->assertSame(QuoteStatus::New, $quote->fresh()->status);
            Queue::assertNothingPushed();
        }
    }

    public function test_transition_is_checked_against_the_current_status_not_a_stale_copy(): void
    {
        $admin = User::factory()->admin()->create();
        $quote = QuoteRequest::factory()->status(QuoteStatus::InReview)->create();

        $staleCopy = $quote->fresh();

        // Someone else closed it first.
        app(QuoteRequestWorkflow::class)->changeStatus($quote, QuoteStatus::Closed, $admin);

        // The stale copy still says "in_review", but the real row is "closed",
        // so in_review -> quoted must be refused.
        $this->expectException(InvalidStatusTransition::class);
        app(QuoteRequestWorkflow::class)->changeStatus($staleCopy, QuoteStatus::Quoted, $admin);
    }

    public function test_delete_event_records_the_current_status(): void
    {
        $admin = User::factory()->admin()->create();
        $quote = QuoteRequest::factory()->status(QuoteStatus::New)->create();
        $staleCopy = $quote->fresh();

        app(QuoteRequestWorkflow::class)->changeStatus($quote, QuoteStatus::InReview, $admin);
        app(QuoteRequestWorkflow::class)->delete($staleCopy, $admin);

        Queue::assertPushed(PublishAuditEvent::class, fn ($job) => $job->event['type'] === 'quote_request.deleted'
            && $job->event['data'] === ['status' => 'in_review']);
    }

    public function test_login_gives_the_same_answer_for_unknown_email_and_wrong_password(): void
    {
        User::factory()->create(['email' => 'real@comtanix.test']);

        $unknown = $this->postJson('/api/login', ['email' => 'nobody@comtanix.test', 'password' => 'x'])->json();
        $wrong = $this->postJson('/api/login', ['email' => 'real@comtanix.test', 'password' => 'x'])->json();

        $this->assertSame($unknown, $wrong);
    }
}
