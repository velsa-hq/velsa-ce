<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this !== self::Completed && $this !== self::Cancelled;
    }

    public function isWorking(): bool
    {
        return $this === self::Assigned || $this === self::InProgress;
    }
}
