<?php

namespace App\Enums;

enum BookableUnit: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case MultiDay = 'multi_day';
    case TimeSlot = 'time_slot';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Daily => 'Daily',
            self::MultiDay => 'Multi-day',
            self::TimeSlot => 'Time slot',
        };
    }
}
