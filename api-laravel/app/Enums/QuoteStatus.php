<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case New = 'new';
    case InReview = 'in_review';
    case Quoted = 'quoted';
    case Closed = 'closed';

    /**
     * The workflow: new -> in_review -> quoted -> closed, plus in_review -> closed.
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

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
