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
