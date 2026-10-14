<?php

namespace App\Queries;

use App\Models\QuoteRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Builds the staff list: search, filters, sorting and pagination.
 */
class QuoteRequestListQuery
{
    private const SORTABLE = ['created_at', 'updated_at', 'client_name', 'status', 'budget_range'];

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Allowed "sort" values: a column, or the column prefixed with "-" for descending.
     *
     * @return list<string>
     */
    public static function sortOptions(): array
    {
        return [...self::SORTABLE, ...array_map(fn ($c) => '-'.$c, self::SORTABLE)];
    }

    /**
     * @param  array<string, mixed>  $filters  validated input from ListQuoteRequestsRequest
     * @return LengthAwarePaginator<int, QuoteRequest>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return QuoteRequest::query()
            // Eager load the assignee to avoid N+1 queries in the resource.
            ->with('assignee:id,name,email,role')
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['service_type'] ?? null, fn ($q, $type) => $q->where('service_type', $type))
            ->when($filters['assigned_to'] ?? null, function ($q, $assignee) {
                $assignee === 'unassigned'
                    ? $q->whereNull('assigned_to')
                    : $q->where('assigned_to', (int) $assignee);
            })
            ->orderBy($column, $direction)
            // Tie-breaker so pages are stable when the sort column has duplicates.
            ->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE))
            ->withQueryString();
    }
}
