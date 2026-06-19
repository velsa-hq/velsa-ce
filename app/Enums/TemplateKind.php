<?php

namespace App\Enums;

enum TemplateKind: string
{
    case Proposal = 'proposal';
    case Contract = 'contract';
    case Addendum = 'addendum';
    case Invoice = 'invoice';
    case PaymentSchedule = 'payment_schedule';

    public function label(): string
    {
        return match ($this) {
            self::Proposal => 'Proposal',
            self::Contract => 'Contract',
            self::Addendum => 'Addendum',
            self::Invoice => 'Invoice',
            self::PaymentSchedule => 'Payment schedule',
        };
    }
}
