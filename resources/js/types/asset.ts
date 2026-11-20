export type AssetStatus =
    | 'available'
    | 'borrowed'
    | 'maintenance'
    | 'broken'
    | 'disposed';

export type AssetCondition = 'good' | 'fair' | 'poor';

export type Option = { id: number; code: string; name: string };

export type CategoryOption = Option & {
    default_useful_life_years: number | null;
};

export type AssetDetail = {
    id: number;
    code: string;
    name: string;
    category: string;
    location: string;
    status: AssetStatus;
    condition: AssetCondition;
    brand: string | null;
    serial_number: string | null;
    purchase_date: string | null;
    purchase_price: string | null;
    useful_life_years: number | null;
    notes: string | null;
};

/** AssetController::show(): one location change (managers only). */
export type MovementRow = {
    id: number;
    from: string;
    to: string;
    moved_at: string;
    reason: string | null;
    recorded_by: string;
};

/** AssetController::show(): one maintenance job (managers only). */
export type MaintenanceRow = {
    id: number;
    description: string;
    vendor: string | null;
    started_at: string;
    completed_at: string | null;
    cost: string | null;
    status: 'in_progress' | 'done';
    recorded_by: string;
};
