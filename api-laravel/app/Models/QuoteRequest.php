<?php

namespace App\Models;

use App\Enums\BudgetRange;
use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use Database\Factories\QuoteRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One quote request (one row in the quote_requests table).
 *
 * Fillable = the fields that may be filled from user input in one go.
 * "status" and "assigned_to" are NOT in that list on purpose, so nobody can
 * send them in the public form. They only change through QuoteRequestWorkflow.
 *
 * Hidden = never shown in JSON (we keep the submitter's IP private).
 */
#[Fillable(['client_name', 'email', 'company', 'service_type', 'budget_range', 'message', 'submitted_ip'])]
#[Hidden(['submitted_ip'])]
class QuoteRequest extends Model
{
    // SoftDeletes: delete() only sets deleted_at; normal queries skip those rows.
    /** @use HasFactory<QuoteRequestFactory> */
    use HasFactory, SoftDeletes;

    /** Every new request starts with status "new". */
    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * Turn database values into PHP types when we read them.
     * e.g. the text "in_review" becomes QuoteStatus::InReview.
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'service_type' => ServiceType::class,
            'budget_range' => BudgetRange::class,
            // Always an int (or null), so the === check in isAssignedTo() is safe.
            'assigned_to' => 'integer',
        ];
    }

    /**
     * The staff member this request is assigned to (or null).
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_to === $user->id;
    }

    /**
     * Search by client name, email or company.
     * Used like: QuoteRequest::search('acme')->get()
     *
     * @param  Builder<QuoteRequest>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        // Escape % and _ so a user typing "50%" searches for that text,
        // instead of it being treated as a LIKE wildcard.
        $like = '%'.addcslashes($term, '%_\\').'%';

        // Wrapped in one where(...) so the ORs don't mix with other filters.
        $query->where(function (Builder $q) use ($like) {
            $q->where('client_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('company', 'like', $like);
        });
    }
}
