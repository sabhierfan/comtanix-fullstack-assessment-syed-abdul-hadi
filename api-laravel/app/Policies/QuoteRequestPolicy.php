<?php

namespace App\Policies;

use App\Models\QuoteRequest;
use App\Models\User;

class QuoteRequestPolicy
{
    /**
     * Admins and staff can see every request (staff need the full queue for context).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QuoteRequest $quoteRequest): bool
    {
        return true;
    }

    /**
     * Staff can only update requests assigned to them; admins can update any.
     */
    public function updateStatus(User $user, QuoteRequest $quoteRequest): bool
    {
        return $user->isAdmin() || $quoteRequest->isAssignedTo($user);
    }

    /**
     * Only admins assign or reassign (confirmed by the reviewer: no self-assignment).
     */
    public function assign(User $user, QuoteRequest $quoteRequest): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, QuoteRequest $quoteRequest): bool
    {
        return $user->isAdmin();
    }
}
