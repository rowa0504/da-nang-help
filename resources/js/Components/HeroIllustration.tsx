interface HeroIllustrationProps {
    className?: string;
}

// A small original illustration hinting at Da Nang (hills, a bridge arc,
// the coastline) plus the pin+bubble "request posted / help is on the way"
// motif — deliberately flat and abstract, not a tourist-brochure scene, and
// entirely inline SVG (no image request, no external asset).
export function HeroIllustration({ className }: HeroIllustrationProps) {
    return (
        <svg viewBox="0 0 480 320" className={className} role="img" aria-hidden="true">
            {/* distant hills */}
            <path d="M0 190 Q80 130 160 190 T320 190 L320 320 L0 320 Z" fill="#E2E8F0" />
            <path d="M120 210 Q220 150 320 210 T480 210 L480 320 L120 320 Z" fill="#CBD5E1" />

            {/* bridge arc */}
            <path d="M60 230 Q240 100 420 230" stroke="#0F766E" strokeWidth="6" fill="none" strokeLinecap="round" />
            <path d="M120 230 L120 205 M180 230 L180 190 M240 230 L240 182 M300 230 L300 190 M360 230 L360 205"
                stroke="#0F766E" strokeWidth="4" strokeLinecap="round" />

            {/* sea */}
            <path d="M0 250 Q60 240 120 250 T240 250 T360 250 T480 250 L480 320 L0 320 Z" fill="#CCFBF1" />
            <path d="M0 265 Q60 255 120 265 T240 265 T360 265 T480 265" stroke="#99F6E4" strokeWidth="3" fill="none" strokeLinecap="round" />

            {/* pin, matching resources/branding/mark.svg, scaled and placed on the bridge */}
            <g transform="translate(206,90) scale(0.9)">
                <path
                    d="M32 58 C20 46 8 34 8 24 C8 12.7 18.7 4 32 4 C45.3 4 56 12.7 56 24 C56 34 44 46 32 58 Z"
                    fill="#0F766E"
                />
                <path d="M20 24 L32 14 L44 24 L44 34 L20 34 Z" fill="#FFFFFF" />
            </g>

            {/* a small "response" bubble beside the pin */}
            <g transform="translate(280,70)">
                <rect x="0" y="0" width="56" height="36" rx="10" fill="#F97316" />
                <path d="M14 36 L10 48 L26 36 Z" fill="#F97316" />
                <path d="M14 14 L42 14 M14 22 L34 22" stroke="#FFFFFF" strokeWidth="3" strokeLinecap="round" />
            </g>
        </svg>
    );
}
