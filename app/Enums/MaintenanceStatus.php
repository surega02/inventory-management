<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case InProgress = 'in_progress';
    case Done = 'done';
}
