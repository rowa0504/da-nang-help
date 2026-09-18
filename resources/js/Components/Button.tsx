import { ComponentPropsWithoutRef } from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'success' | 'link';
export type ButtonSize = 'default' | 'compact';

interface ButtonProps extends ComponentPropsWithoutRef<'button'> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
}

const VARIANT_CLASSES: Record<ButtonVariant, string> = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700',
    secondary: 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50',
    danger: 'bg-red-600 text-white hover:bg-red-700',
    success: 'bg-green-600 text-white hover:bg-green-700',
    link: 'text-brand-600 underline hover:text-brand-700',
};

// min-h-11 (44px) guarantees a touch-friendly tap target on mobile; above
// the sm breakpoint the button is free to size to its content again.
const SIZE_CLASSES: Record<ButtonVariant, Record<ButtonSize, string>> = {
    primary: { default: 'rounded px-4 py-2 min-h-11 sm:min-h-0', compact: 'rounded px-3 py-1.5 text-sm min-h-11 sm:min-h-0' },
    secondary: { default: 'rounded px-4 py-2 min-h-11 sm:min-h-0', compact: 'rounded px-3 py-1.5 text-sm min-h-11 sm:min-h-0' },
    danger: { default: 'rounded px-4 py-2 min-h-11 sm:min-h-0', compact: 'rounded px-3 py-1.5 text-sm min-h-11 sm:min-h-0' },
    success: { default: 'rounded px-4 py-2 min-h-11 sm:min-h-0', compact: 'rounded px-3 py-1.5 text-sm min-h-11 sm:min-h-0' },
    link: { default: '', compact: 'text-sm' },
};

const BASE_CLASSES =
    'inline-flex items-center justify-center gap-2 font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:cursor-not-allowed disabled:opacity-50';

// Shared by <Button> and by any Inertia <Link> that needs to *look* like a
// button — a <Link> renders an <a>, which can't contain a real <button>
// (two nested focusable/interactive elements), so those call sites apply
// these classes directly to the <a> instead of wrapping <Button>. This is
// the single source of truth for button styling either way — no page
// should hand-roll its own button class string.
export function buttonClasses(variant: ButtonVariant = 'primary', size: ButtonSize = 'default', className = ''): string {
    return [BASE_CLASSES, VARIANT_CLASSES[variant], SIZE_CLASSES[variant][size], className].filter(Boolean).join(' ');
}

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
    return (
        <button className={buttonClasses(variant, size, className)} disabled={disabled || loading} aria-busy={loading || undefined} {...rest}>
            {loading && <Spinner />}
            {children}
        </button>
    );
}
