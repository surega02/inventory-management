export type Role = 'admin' | 'warehouse_staff' | 'employee';

/** Exactly what HandleInertiaRequests shares; nothing else about the user reaches the browser. */
export type User = {
    id: number;
    name: string;
    email: string;
    role: Role | null;
    avatar?: string;
};

export type Auth = {
    user: User;
};

/** Menu visibility hints; the server still checks every request. */
export type Can = {
    manage_assets: boolean;
    manage_users: boolean;
    view_audit: boolean;
};
