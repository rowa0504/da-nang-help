interface LogoMarkProps {
    className?: string;
    alt?: string;
}

// References the generated public/favicon.svg (built from
// resources/branding/mark.svg by scripts/generate-favicons.mjs) rather than
// duplicating the same path data as inline JSX — the mark's geometry has
// exactly one source of truth. `alt` defaults to empty because in normal
// use LogoMark sits next to the "Da Nang Help" text in <Logo>, which
// already carries the accessible name; pass an explicit `alt` only when
// using LogoMark on its own with no adjacent text.
export function LogoMark({ className, alt = '' }: LogoMarkProps) {
    return <img src="/favicon.svg" alt={alt} width={32} height={32} className={className} />;
}

export function Logo({ className }: { className?: string }) {
    return (
        <span className={['inline-flex items-center gap-2', className].filter(Boolean).join(' ')}>
            <LogoMark />
            <span className="text-lg font-semibold text-slate-900">
                Da Nang <span className="text-brand-600">Help</span>
            </span>
        </span>
    );
}
