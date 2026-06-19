<?php

namespace App\Enums;

/** Sales pipeline stages; case order drives the Kanban board and progress metrics. */
enum LeadStage: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case ProposalSent = 'proposal_sent';
    case ContractSent = 'contract_sent';
    case Won = 'won';
    case Lost = 'lost';

    public function isTerminal(): bool
    {
        return $this === self::Won || $this === self::Lost;
    }

    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }

    public function defaultProbability(): float
    {
        return match ($this) {
            self::New => 0.10,
            self::Qualified => 0.25,
            self::ProposalSent => 0.50,
            self::ContractSent => 0.80,
            self::Won => 1.00,
            self::Lost => 0.00,
        };
    }
}
