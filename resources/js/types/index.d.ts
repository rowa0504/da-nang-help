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
    flash: {
        warning: string | null;
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

export type ServiceRequestStatus = 'open' | 'assigned' | 'cancelled';
export type ServiceRequestUrgency = 'normal' | 'urgent';

export interface ServiceRequestPhoto {
    id: number;
    url: string;
}

export interface ServiceRequestData {
    id: number;
    title: string;
    description: string;
    category: { id: number; name: string };
    area: { id: number; name: string };
    urgency: ServiceRequestUrgency;
    status: ServiceRequestStatus;
    created_at: string;
    photos: ServiceRequestPhoto[];
    // Present only when the viewer is the posting Customer or an Admin —
    // the backend Resource decides this, the frontend just renders
    // whatever fields happen to be present (this is a display convenience,
    // not a security boundary; see Dashboard.tsx for the same note).
    address_text?: string;
    lat?: number;
    lng?: number;
    customer?: { name: string; email: string; phone: string | null };
}

export interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
}

export interface PaginatedData<T> {
    data: T[];
    links: PaginationLinks;
    meta: PaginationMeta;
}

export type OfferStatus = 'pending' | 'accepted' | 'rejected' | 'withdrawn' | 'cancelled';

export interface OfferData {
    id: number;
    price: string; // decimal cast string, kept as-is — never converted to number
    currency: string;
    message: string;
    available_at: string | null; // UTC ISO 8601
    status: OfferStatus;
    created_at: string;
    provider: { id: number; business_name: string | null; avg_rating: string; completed_jobs_count: number };
    // Present only for the offering Provider themself or an Admin.
    original_message?: string;
    source_locale?: string;
}
