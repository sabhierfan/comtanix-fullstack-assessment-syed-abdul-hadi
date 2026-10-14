<?php

namespace App\Policies;

use App\Models\QuoteRequest;
use App\Models\User;

/**
 * The permission rulebook for quote requests. All "who can do what" rules are here.
 *
 * Laravel finds this class automatically (QuoteRequest -> QuoteRequestPolicy).
 * It is used by:
 *   - Gate::authorize('view', $quote)       in controllers
 *   - $user->can('updateStatus', $quote)     in form requests and the resource
 * If a method returns false, the user gets 403 Forbidden.
 */
class QuoteRequestPolicy
{
    /** Everyone logged in can see the list (staff need the full queue for context). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Everyone logged in can open any request. */
    public function view(User $user, QuoteRequest $quoteRequest): bool
    {
        return true;
    }

    /** Admin can change any request. Staff only the ones assigned to them. */
    public function updateStatus(User $user, QuoteRequest $quoteRequest): bool
    {
        return $user->isAdmin() || $quoteRequest->isAssignedTo($user);
    }

    /** Only admin can assign or reassign. Staff can't even assign to themselves (confirmed by the reviewer). */
    public function assign(User $user, QuoteRequest $quoteRequest): bool
    {
        return $user->isAdmin();
    }

    /** Only admin can delete (soft delete). */
    public function delete(User $user, QuoteRequest $quoteRequest): bool
    {
        return $user->isAdmin();
    }
}
