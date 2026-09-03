export type UserRole = 'customer' | 'provider' | 'admin';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    locale: string;
}

export interface SharedProps {
    auth: {
        user: AuthUser | null;
    };
    [key: string]: unknown;
}
