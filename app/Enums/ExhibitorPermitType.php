<?php

namespace App\Enums;

/** Booth activities needing venue sign-off; Other is the free-text catch-all. */
enum ExhibitorPermitType: string
{
    case FoodSampling = 'food_sampling';
    case AlcoholService = 'alcohol_service';
    case OpenFlame = 'open_flame';
    case VehicleMoveIn = 'vehicle_move_in';
    case AmplifiedSound = 'amplified_sound';
    case OversizedDisplay = 'oversized_display';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FoodSampling => 'Food / beverage sampling',
            self::AlcoholService => 'Alcohol service',
            self::OpenFlame => 'Open flame / cooking',
            self::VehicleMoveIn => 'Vehicle move-in / display',
            self::AmplifiedSound => 'Amplified sound',
            self::OversizedDisplay => 'Oversized / hanging display',
            self::Other => 'Other special request',
        };
    }
}
