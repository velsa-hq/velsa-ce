<?php

namespace App\Enums;

enum SupportRequestCategory: string
{
    case Question = 'question';
    case Problem = 'problem';
    case Suggestion = 'suggestion';

    public function label(): string
    {
        return match ($this) {
            self::Question => 'Question',
            self::Problem => 'Problem',
            self::Suggestion => 'Suggestion',
        };
    }
}
