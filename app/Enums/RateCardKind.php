<?php

namespace App\Enums;

enum RateCardKind: string
{
    case Standard = 'standard';
    case Nonprofit = 'nonprofit';
    case Government = 'government';
    case Member = 'member';
    case PeakSeason = 'peak_season';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Nonprofit => 'Nonprofit',
            self::Government => 'Government',
            self::Member => 'Member',
            self::PeakSeason => 'Peak season',
            self::Holiday => 'Holiday',
        };
    }
}
