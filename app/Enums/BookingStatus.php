<?php

namespace App\Enums;

/** Booking lifecycle; case order drives calendar rendering. */
enum BookingStatus: string
{
    case Inquiry = 'inquiry';
    case Hold = 'hold';
    case Tentative = 'tentative';
    case Definite = 'definite';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function blocksOverlap(): bool
    {
        return $this === self::Definite || $this === self::Completed;
    }

    public function isOpen(): bool
    {
        return $this !== self::Completed && $this !== self::Cancelled;
    }
}
