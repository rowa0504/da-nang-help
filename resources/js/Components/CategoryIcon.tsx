import { AirVent, Droplets, GraduationCap, Sparkles, Truck, Wrench, Zap, LucideIcon } from 'lucide-react';

interface CategoryIconProps {
    slug: string;
    className?: string;
}

// Categories have no icon column (confirmed during Phase 10A-2 planning),
// so the slug->icon mapping lives here, client-side. Any slug not covered
// below falls back to a generic tool icon — this must degrade gracefully
// since production category content may differ entirely from the seeded
// dev/test data these slugs were drawn from.
//
// motorbike-repair intentionally uses the generic tool icon (Wrench)
// rather than a bicycle/motorbike glyph — a bike icon reads as "cycling",
// not "repair service", at a glance.
const ICONS_BY_SLUG: Record<string, LucideIcon> = {
    'motorbike-repair': Wrench,
    'aircon-repair': AirVent,
    cleaning: Sparkles,
    education: GraduationCap,
    plumbing: Droplets,
    'electrical-work': Zap,
    moving: Truck,
};

const FALLBACK_ICON: LucideIcon = Wrench;

export function CategoryIcon({ slug, className }: CategoryIconProps) {
    const Icon = ICONS_BY_SLUG[slug] ?? FALLBACK_ICON;
    return <Icon aria-hidden="true" className={className} />;
}
