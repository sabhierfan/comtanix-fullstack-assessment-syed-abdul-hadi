<?php

namespace App\Http\Requests;

use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use App\Queries\QuoteRequestListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checks the list filters in the URL (?status=new&sort=-created_at...).
 * Only known values are accepted, so nobody can sort by a secret column
 * or ask for 1,000,000 rows per page (max 100).
 */
class ListQuoteRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(QuoteStatus::class)],
            'service_type' => ['nullable', Rule::enum(ServiceType::class)],
            // A user id, or "unassigned".
            'assigned_to' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== 'unassigned' && ! ctype_digit((string) $value)) {
                    $fail('The assigned_to filter must be a user id or "unassigned".');
                }
            }],
            'sort' => ['nullable', Rule::in(QuoteRequestListQuery::sortOptions())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
