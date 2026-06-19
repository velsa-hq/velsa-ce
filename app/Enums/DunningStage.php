<?php

namespace App\Enums;

/** Dunning escalation tier per invoice; advanced nightly by DunningCommand. */
enum DunningStage: string
{
    case None = 'none';
    case FirstNotice = 'first_notice';
    case SecondNotice = 'second_notice';
    case FinalNotice = 'final_notice';
    case Collections = 'collections';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No reminders sent',
            self::FirstNotice => '1st notice',
            self::SecondNotice => '2nd notice',
            self::FinalNotice => 'Final notice',
            self::Collections => 'Collections',
        };
    }

    public static function fromDaysPastDue(int $days): self
    {
        return match (true) {
            $days <= 0 => self::None,
            $days <= 7 => self::FirstNotice,
            $days <= 30 => self::SecondNotice,
            $days <= 60 => self::FinalNotice,
            default => self::Collections,
        };
    }
}
