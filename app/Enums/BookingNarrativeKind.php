<?php

namespace App\Enums;

enum BookingNarrativeKind: string
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case SiteVisit = 'site_visit';
    case Decision = 'decision';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::Call => 'Call',
            self::Email => 'Email',
            self::Meeting => 'Meeting',
            self::SiteVisit => 'Site visit',
            self::Decision => 'Decision',
            self::System => 'System',
        };
    }
}
