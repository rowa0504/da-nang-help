import { ReactNode } from 'react';

export type BadgeVariant = 'success' | 'warning' | 'danger' | 'neutral' | 'info';

interface BadgeProps {
    variant: BadgeVariant;
    children: ReactNode;
}

// Badge deliberately knows nothing about domain enums (ServiceRequestStatus,
// OfferStatus, etc.) — each call site owns its own Record<Enum, BadgeVariant>
// correspondence table, mirroring the existing Record<Enum, TranslationKey>
// convention used throughout the app.
const VARIANT_CLASSES: Record<BadgeVariant, string> = {
    success: 'bg-green-50 text-green-800 ring-1 ring-inset ring-green-200',
    warning: 'bg-yellow-50 text-yellow-800 ring-1 ring-inset ring-yellow-200',
    danger: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
    neutral: 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200',
    info: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
};

export function Badge({ variant, children }: BadgeProps) {
    return (
        <span className={['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', VARIANT_CLASSES[variant]].join(' ')}>
            {children}
        </span>
    );
}
