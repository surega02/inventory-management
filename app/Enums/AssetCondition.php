<?php

namespace App\Enums;

/** Used for assets.condition and loans.return_condition. */
enum AssetCondition: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
}
