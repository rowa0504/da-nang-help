export type UserRole = 'customer' | 'provider' | 'admin';

export type ProviderVerificationStatus = 'pending' | 'approved' | 'rejected' | 'suspended';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    locale: string;
    has_provider_profile?: boolean;
    provider_verification_status?: ProviderVerificationStatus | null;
}

export interface SharedProps {
    auth: {
        user: AuthUser | null;
    };
    [key: string]: unknown;
}

export interface CategoryOption {
    id: number;
    slug: string;
    name: string;
}

export interface AreaOption {
    id: number;
    name: string;
    slug: string;
}

export interface ProviderProfileData {
    business_name: string;
    bio: string | null;
    verification_status: ProviderVerificationStatus;
    category_ids: number[];
    area_ids: number[];
}
