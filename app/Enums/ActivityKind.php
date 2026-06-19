<?php

namespace App\Enums;

enum ActivityKind: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Note = 'note';
    case Task = 'task';
    case SiteVisit = 'site_visit';
}
