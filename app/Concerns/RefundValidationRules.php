<?php

namespace App\Concerns;

trait RefundValidationRules
{
    /**
     * @return array<int, string>
     */
    abstract protected function reasonRule(bool $required): array;

    /**
     * Validation rules shared by every partial-or-full refund action: a
     * positive integer cents amount plus an optional free-text reason. Pairs
     * with ReasonValidationRules (which the using class must also apply) so the
     * reason fragment stays defined in one place.
     *
     * @return array<string, array<int, string>>
     */
    protected function refundRules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => $this->reasonRule(false),
        ];
    }
}
