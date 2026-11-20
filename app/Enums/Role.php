<?php

namespace App\Enums;

/** The three seeded spatie roles; one user has exactly one. */
enum Role: string
{
    case Admin = 'admin';
    case WarehouseStaff = 'warehouse_staff';
    case Employee = 'employee';
}
