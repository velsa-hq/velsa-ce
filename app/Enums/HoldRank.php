<?php

namespace App\Enums;

/**
 * Hold rank queues multiple tentative bookings on one space.
 * Only one definite booking may occupy a space; holds rank behind it.
 */
enum HoldRank: string
{
    case First = '1st';
    case Second = '2nd';
    case Third = '3rd';
}
