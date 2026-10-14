<?php

namespace App\Services;

use App\Enums\AuditEventType;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Services\Audit\AuditEventPublisher;
use Illuminate\Support\Facades\DB;

/**
 * Every state change of a quote request goes through here, so the workflow
 * rules and audit events live in one place. Authorization is done before this
 * class is called (policies + form requests).
 */
class QuoteRequestWorkflow
{
    public function __construct(private readonly AuditEventPublisher $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function submit(array $attributes, ?string $ip): QuoteRequest
    {
        return DB::transaction(function () use ($attributes, $ip) {
            $quoteRequest = QuoteRequest::create([...$attributes, 'submitted_ip' => $ip]);

            $this->audit->publish(AuditEventType::Created, $quoteRequest, null, [
                'status' => $quoteRequest->status->value,
                'serviceType' => $quoteRequest->service_type->value,
                'budgetRange' => $quoteRequest->budget_range->value,
            ]);

            return $quoteRequest;
        });
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function changeStatus(QuoteRequest $quoteRequest, QuoteStatus $to, User $actor): QuoteRequest
    {
        return DB::transaction(function () use ($quoteRequest, $to, $actor) {
            // Lock the row so two concurrent updates cannot both pass the transition check.
            $locked = QuoteRequest::whereKey($quoteRequest->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

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

    public function assign(QuoteRequest $quoteRequest, ?User $assignee, User $actor): QuoteRequest
    {
        return DB::transaction(function () use ($quoteRequest, $assignee, $actor) {
            $locked = QuoteRequest::whereKey($quoteRequest->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->assigned_to;

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

    public function delete(QuoteRequest $quoteRequest, User $actor): void
    {
        DB::transaction(function () use ($quoteRequest, $actor) {
            $quoteRequest->delete();

            $this->audit->publish(AuditEventType::Deleted, $quoteRequest, $actor, [
                'status' => $quoteRequest->status->value,
            ]);
        });
    }
}
