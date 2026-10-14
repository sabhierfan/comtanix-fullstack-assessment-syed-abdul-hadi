<?php

namespace App\Http\Requests;

use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checks a "change status" request BEFORE the controller runs.
 *   authorize() -> may this user change this request? (no = 403)
 *   rules()     -> is "status" one of the 4 real statuses? (no = 422)
 * Whether the MOVE itself is allowed (e.g. new -> quoted) is checked later in the workflow.
 */
class UpdateQuoteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('quoteRequest'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(QuoteStatus::class)],
        ];
    }

    public function status(): QuoteStatus
    {
        return QuoteStatus::from($this->validated('status'));
    }
}
