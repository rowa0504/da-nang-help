export type UserRole = 'customer' | 'provider' | 'admin';

export type ProviderVerificationStatus = 'pending' | 'approved' | 'rejected' | 'suspended';

// Keep in sync with SetLocale::SUPPORTED (app/Http/Middleware/SetLocale.php).
export type SupportedLocale = 'en' | 'ja' | 'vi';

// Matches the {is_translated, source_locale, original} shape added to
// ServiceRequestResource/OfferResource in Phase 8.
export interface TranslationMeta {
    is_translated: boolean;
    source_locale: string;
    original: string;
}

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
    locale: SupportedLocale;
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
    avg_rating: string; // decimal cast string, kept as-is — never converted to number
    completed_jobs_count: number;
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
    title_translation: TranslationMeta;
    description_translation: TranslationMeta;
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
    message_translation: TranslationMeta;
    available_at: string | null; // UTC ISO 8601
    status: OfferStatus;
    created_at: string;
    provider: { id: number; business_name: string | null; avg_rating: string; completed_jobs_count: number };
    // Present only for the offering Provider themself or an Admin.
    original_message?: string;
    source_locale?: string;
}

export type JobStatus = 'assigned' | 'in_progress' | 'awaiting_confirmation' | 'completed' | 'cancelled';

export interface JobData {
    id: number;
    agreed_price: string; // decimal cast string, kept as-is — never converted to number
    currency: string;
    status: JobStatus;
    provider_completed_at: string | null;
    customer_confirmed_at: string | null;
    auto_confirm_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    // Only reachable once JobPolicy::view() has confirmed the viewer is this
    // job's Customer, its Provider, or an Admin — see JobResource, and the
    // same note on ServiceRequestData above.
    service_request: { id: number; title: string; address_text: string; lat: number; lng: number };
    customer: { name: string; phone: string | null };
    provider: { name: string; phone: string | null; business_name: string | null };
    // null if no review yet, or if a hidden review is being withheld from
    // this viewer (the Provider being reviewed never sees a hidden one).
    review: ReviewData | null;
}

export interface ReviewData {
    id: number;
    rating: number;
    comment: string | null;
    is_hidden: boolean;
    created_at: string;
}

export interface AdminReviewData {
    id: number;
    job_id: number;
    rater_name: string;
    ratee_name: string;
    rating: number;
    comment: string | null;
    is_hidden: boolean;
    created_at: string;
}
