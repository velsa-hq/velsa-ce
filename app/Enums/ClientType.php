<?php

namespace App\Enums;

enum ClientType: string
{
    case Individual = 'individual';
    case Business = 'business';
    case Government = 'government';
    case Nonprofit = 'nonprofit';
    case Educational = 'educational';
}
