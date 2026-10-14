<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checks an "assign" request: only admins (policy), and assigned_to must be
 * a real user id or null (null = unassign).
 */
class AssignQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('quoteRequest'));
    }

    /**
     * assigned_to: a user id, or null to unassign.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['present', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function assignee(): ?User
    {
        $id = $this->validated('assigned_to');

        return $id === null ? null : User::findOrFail($id);
    }
}
