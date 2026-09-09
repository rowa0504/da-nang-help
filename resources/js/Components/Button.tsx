import { ComponentPropsWithoutRef } from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'success' | 'link';
export type ButtonSize = 'default' | 'compact';

interface ButtonProps extends ComponentPropsWithoutRef<'button'> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
}

const VARIANT_CLASSES: Record<ButtonVariant, string> = {
    primary: 'bg-blue-600 text-white hover:bg-blue-700',
    secondary: 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
    danger: 'bg-red-600 text-white hover:bg-red-700',
    success: 'bg-green-600 text-white hover:bg-green-700',
    link: 'text-blue-600 underline hover:text-blue-800',
};

const SIZE_CLASSES: Record<ButtonVariant, Record<ButtonSize, string>> = {
    primary: { default: 'rounded px-4 py-2', compact: 'rounded px-3 py-1.5 text-sm' },
    secondary: { default: 'rounded px-4 py-2', compact: 'rounded px-3 py-1.5 text-sm' },
    danger: { default: 'rounded px-4 py-2', compact: 'rounded px-3 py-1.5 text-sm' },
    success: { default: 'rounded px-4 py-2', compact: 'rounded px-3 py-1.5 text-sm' },
    link: { default: '', compact: 'text-sm' },
};

// Spinner is a plain SVG (no icon library) so it works entirely offline
// and doesn't add a dependency for a single glyph.
function Spinner() {
    return (
        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>
    );
}

export function Button({ variant = 'primary', size = 'default', loading = false, disabled, className = '', children, ...rest }: ButtonProps) {
    const classes = [
        'inline-flex items-center justify-center gap-2 font-medium disabled:cursor-not-allowed disabled:opacity-50',
        VARIANT_CLASSES[variant],
        SIZE_CLASSES[variant][size],
        className,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <button className={classes} disabled={disabled || loading} aria-busy={loading || undefined} {...rest}>
            {loading && <Spinner />}
            {children}
        </button>
    );
}
