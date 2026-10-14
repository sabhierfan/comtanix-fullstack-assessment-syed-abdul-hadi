<?php

namespace App\Http\Requests;

use App\Enums\BudgetRange;
use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public quote submission. The "website" field is a honeypot: it is hidden
 * from humans in the form, so any value means the submitter is a bot.
 */
class StoreQuoteRequestRequest extends FormRequest
{
    public const HONEYPOT = 'website';

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
            'client_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'company' => ['nullable', 'string', 'max:150'],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'budget_range' => ['required', Rule::enum(BudgetRange::class)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            self::HONEYPOT => ['nullable', 'string', 'max:255'],
        ];
    }

    public function isSpam(): bool
    {
        return $this->filled(self::HONEYPOT);
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteAttributes(): array
    {
        return collect($this->validated())->except(self::HONEYPOT)->all();
    }
}
