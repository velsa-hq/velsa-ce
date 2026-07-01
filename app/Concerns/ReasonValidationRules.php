<?php

namespace App\Concerns;

trait ReasonValidationRules
{
    /**
     * Validation rules for an audit "reason for action" note. Required-ness
     * varies per action (required for a write-off / void, optional for a draft
     * addendum or refund), so the caller passes it explicitly.
     *
     * @return array<int, string>
     */
    protected function reasonRule(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:500'];
    }
}
