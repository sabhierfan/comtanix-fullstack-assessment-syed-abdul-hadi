<?php

namespace App\Http\Resources;

use App\Enums\QuoteStatus;
use App\Models\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Decides what one quote request looks like in JSON.
 * We pick the fields ourselves, so private ones (like submitted_ip) are never sent.
 *
 * @mixin QuoteRequest
 */
class QuoteRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'client_name' => $this->client_name,
            'email' => $this->email,
            'company' => $this->company,
            'service_type' => $this->service_type,
            'budget_range' => $this->budget_range,
            'message' => $this->message,
            'status' => $this->status,
            // Which statuses this request can move to next. The dashboard shows one button per value.
            'allowed_transitions' => array_map(
                fn (QuoteStatus $s) => $s->value,
                $this->status->allowedTransitions(),
            ),
            'assigned_to' => $this->assigned_to,
            // Only included if we loaded it with ->with('assignee') (prevents extra queries).
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            // Lets the dashboard show or hide actions without duplicating the policy rules.
            'can' => [
                'update_status' => (bool) $user?->can('updateStatus', $this->resource),
                'assign' => (bool) $user?->can('assign', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
