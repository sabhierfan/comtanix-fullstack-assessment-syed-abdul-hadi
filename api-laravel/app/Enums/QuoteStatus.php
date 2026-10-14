<?php

namespace App\Enums;

/**
 * The 4 possible statuses of a quote request, and which moves between them are allowed.
 */
enum QuoteStatus: string
{
    case New = 'new';
    case InReview = 'in_review';
    case Quoted = 'quoted';
    case Closed = 'closed';

    /**
     * Where can a request go from here?
     *
     *   new       -> in_review
     *   in_review -> quoted, or closed
     *   quoted    -> closed
     *   closed    -> nowhere (final)
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::InReview],
            self::InReview => [self::Quoted, self::Closed],
            self::Quoted => [self::Closed],
            self::Closed => [],
        };
    }

    /** true if moving from this status to $next is allowed. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
