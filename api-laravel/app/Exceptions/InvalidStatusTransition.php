<?php

namespace App\Exceptions;

use App\Enums\QuoteStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvalidStatusTransition extends RuntimeException
{
    public function __construct(
        public readonly QuoteStatus $from,
        public readonly QuoteStatus $to,
    ) {
        parent::__construct("Cannot change status from \"{$from->value}\" to \"{$to->value}\".");
    }

    public function render(): JsonResponse
    {
        $allowed = array_map(fn (QuoteStatus $s) => $s->value, $this->from->allowedTransitions());

        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'status' => [
                    $allowed
                        ? $this->getMessage().' Allowed: '.implode(', ', $allowed).'.'
                        : $this->getMessage().' The request is already closed.',
                ],
            ],
            'current_status' => $this->from->value,
            'allowed_transitions' => $allowed,
        ], 422);
    }
}
