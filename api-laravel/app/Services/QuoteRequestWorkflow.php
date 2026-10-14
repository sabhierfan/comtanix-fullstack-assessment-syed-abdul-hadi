<?php

namespace App\Services;

use App\Enums\AuditEventType;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Audit\AuditEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The heart of the app. EVERY change to a quote request goes through this class:
 * submit, change status, assign, and delete.
 *
 * Why one class? So the rules and the audit events live in one place.
 * Controllers stay small; they just call these methods.
 *
 * Each method works the same way:
 *   1. Open a database transaction ("do all of this, or none of it").
 *   2. Lock the row so two people can't change it at the same moment.
 *   3. Check the rules.
 *   4. Save the change.
 *   5. Queue an audit event in the SAME transaction (so it is never lost).
 */
class QuoteRequestWorkflow
{
    public function __construct(private readonly AuditEventPublisher $audit) {}

    /**
     * A customer submitted the public form. Save it and record a "created" event.
     *
     * @param  array<string, mixed>  $attributes  validated form fields
     */
    public function submit(array $attributes, ?string $ip): QuoteRequest
    {
        return DB::transaction(function () use ($attributes, $ip) {
            $quoteRequest = QuoteRequest::create([...$attributes, 'submitted_ip' => $ip]);

            // actor = null because a guest (not a staff user) did this.
            $this->audit->publish(AuditEventType::Created, $quoteRequest, null, [
                'status' => $quoteRequest->status->value,
                'serviceType' => $quoteRequest->service_type->value,
                'budgetRange' => $quoteRequest->budget_range->value,
            ]);

            return $quoteRequest;
        });
    }

    /**
     * Move a request to a new status, e.g. new -> in_review.
     *
     * @throws InvalidStatusTransition when the move is not allowed (becomes a 422)
     */
    public function changeStatus(QuoteRequest $quoteRequest, QuoteStatus $to, User $actor): QuoteRequest
    {
        return DB::transaction(function () use ($quoteRequest, $to, $actor) {
            // Lock the row (SELECT ... FOR UPDATE). If someone else is changing the
            // same request right now, we wait here until they finish.
            // This stops two clicks at the same time from both passing the check.
            $locked = QuoteRequest::whereKey($quoteRequest->id)->lockForUpdate()->firstOrFail();

            // Check permission again on the locked (fresh) row.
            // The first check happened before the lock. If an admin re-assigned the
            // request in between, this staff member must not be allowed any more.
            Gate::forUser($actor)->authorize('updateStatus', $locked);

            $from = $locked->status;

            // The allowed moves are defined in QuoteStatus::allowedTransitions().
            if (! $from->canTransitionTo($to)) {
                throw new InvalidStatusTransition($from, $to);
            }

            $locked->status = $to;
            $locked->save();

            $this->audit->publish(AuditEventType::StatusChanged, $locked, $actor, [
                'from' => $from->value,
                'to' => $to->value,
            ]);

            return $locked;
        });
    }

    /**
     * Give a request to a user, or take it away (assignee = null).
     * Only admins get here; the policy checks that first.
     */
    public function assign(QuoteRequest $quoteRequest, ?User $assignee, User $actor): QuoteRequest
    {
        return DB::transaction(function () use ($quoteRequest, $assignee, $actor) {
            $locked = QuoteRequest::whereKey($quoteRequest->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->assigned_to;

            // Same person as before: nothing changes, so no audit event either.
            if ($previous === $assignee?->id) {
                return $locked;
            }

            $locked->assigned_to = $assignee?->id;
            $locked->save();

            $this->audit->publish(AuditEventType::Assigned, $locked, $actor, [
                'from' => $previous,
                'to' => $assignee?->id,
            ]);

            return $locked;
        });
    }

    /**
     * Soft delete: the row stays in the database with a deleted_at date,
     * but the app treats it as gone (404). Only admins get here.
     */
    public function delete(QuoteRequest $quoteRequest, User $actor): void
    {
        DB::transaction(function () use ($quoteRequest, $actor) {
            // Lock first, so the status we write in the event is the real, current one.
            $locked = QuoteRequest::whereKey($quoteRequest->id)->lockForUpdate()->firstOrFail();

            $locked->delete();

            $this->audit->publish(AuditEventType::Deleted, $locked, $actor, [
                'status' => $locked->status->value,
            ]);
        });
    }
}
