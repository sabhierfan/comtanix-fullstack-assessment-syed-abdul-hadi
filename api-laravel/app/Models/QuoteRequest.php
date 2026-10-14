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

#[Fillable(['client_name', 'email', 'company', 'service_type', 'budget_range', 'message', 'submitted_ip'])]
#[Hidden(['submitted_ip'])]
class QuoteRequest extends Model
{
    /** @use HasFactory<QuoteRequestFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Status and assignment are deliberately not mass assignable:
     * they only change through App\Services\QuoteRequestWorkflow.
     */
    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'service_type' => ServiceType::class,
            'budget_range' => BudgetRange::class,
        ];
    }

    /**
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
     *
     * @param  Builder<QuoteRequest>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $q) use ($like) {
            $q->where('client_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('company', 'like', $like);
        });
    }
}
