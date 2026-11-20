<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Available = 'available';
    case Borrowed = 'borrowed';
    case Maintenance = 'maintenance';
    case Broken = 'broken';
    case Disposed = 'disposed';
}
