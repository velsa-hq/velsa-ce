<?php

namespace App\Enums;

/** Coverage type a certificate attests to. */
enum InsurancePolicyType: string
{
    case GeneralLiability = 'general_liability';
    case WorkersComp = 'workers_comp';
    case Auto = 'auto';
    case Umbrella = 'umbrella';
    case EventCancellation = 'event_cancellation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GeneralLiability => 'General liability',
            self::WorkersComp => 'Workers compensation',
            self::Auto => 'Commercial auto',
            self::Umbrella => 'Umbrella / excess',
            self::EventCancellation => 'Event cancellation',
            self::Other => 'Other',
        };
    }
}
