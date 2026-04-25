export type User = {
    id: number;
    name: string;
    username: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
    isAdmin: boolean;
    isStaff: boolean;
    canManageUsers: boolean;
    role: 'admin' | 'staff' | 'user' | null;
    balanceVnd: number;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
