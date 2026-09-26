import { Head, Link, router } from '@inertiajs/react';
import { ChangeEvent, useState } from 'react';
import { Search } from 'lucide-react';
import { AreaOption, CategoryOption, MatchLevel, PaginatedData, ServiceRequestData, ServiceRequestUrgency } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { EmptyState } from '@/Components/EmptyState';
import { PaginationNav } from '@/Components/PaginationNav';
import { Select } from '@/Components/Select';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface Filters {
    recommended: boolean;
    category_id: number | null;
    area_id: number | null;
}

interface Props {
    requests: PaginatedData<ServiceRequestData>;
    filters: Filters;
    categoryOptions: CategoryOption[];
    areaOptions: AreaOption[];
}

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const URGENCY_KEYS: Record<ServiceRequestUrgency, TranslationKey> = {
    normal: 'status.urgency.normal',
    urgent: 'status.urgency.urgent',
};

// "none" deliberately has no entry — full shows "recommended", partial
// shows "partial match", a full mismatch shows no badge at all.
const MATCH_BADGE: Partial<Record<MatchLevel, { labelKey: TranslationKey; variant: BadgeVariant }>> = {
    full: { labelKey: 'provider.requests.index.match_full', variant: 'success' },
    partial: { labelKey: 'provider.requests.index.match_partial', variant: 'neutral' },
};

export default function Index({ requests, filters, categoryOptions, areaOptions }: Props) {
    const { t } = useTranslation();
    const [navigating, setNavigating] = useState(false);

    function applyFilters(next: Partial<Filters>) {
        if (navigating) {
            return;
        }
        const merged = { ...filters, ...next };
        setNavigating(true);
        router.get(
            '/provider/requests',
            {
                recommended: merged.recommended ? '1' : undefined,
                category_id: merged.category_id ?? undefined,
                area_id: merged.area_id ?? undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true, onFinish: () => setNavigating(false) },
        );
    }

    function onRecommendedChange(e: ChangeEvent<HTMLInputElement>) {
        applyFilters({ recommended: e.target.checked });
    }

    function onCategoryChange(e: ChangeEvent<HTMLSelectElement>) {
        applyFilters({ category_id: e.target.value === '' ? null : Number(e.target.value) });
    }

    function onAreaChange(e: ChangeEvent<HTMLSelectElement>) {
        applyFilters({ area_id: e.target.value === '' ? null : Number(e.target.value) });
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('provider.requests.index.title')} />
                <PageHeader title={t('provider.requests.index.heading')} />

                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            checked={filters.recommended}
                            onChange={onRecommendedChange}
                            disabled={navigating}
                            className="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600"
                        />
                        {t('provider.requests.index.recommended_only')}
                    </label>

                    <Select
                        value={filters.category_id ?? ''}
                        onChange={onCategoryChange}
                        disabled={navigating}
                        className="mt-0 w-auto"
                        aria-label={t('common.category')}
                    >
                        <option value="">{t('provider.requests.index.all_categories')}</option>
                        {categoryOptions.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </Select>

                    <Select
                        value={filters.area_id ?? ''}
                        onChange={onAreaChange}
                        disabled={navigating}
                        className="mt-0 w-auto"
                        aria-label={t('common.area')}
                    >
                        <option value="">{t('provider.requests.index.all_areas')}</option>
                        {areaOptions.map((area) => (
                            <option key={area.id} value={area.id}>
                                {area.name}
                            </option>
                        ))}
                    </Select>
                </div>

                {requests.data.length === 0 ? (
                    <EmptyState
                        icon={<Search className="h-8 w-8" />}
                        title={t('provider.requests.index.empty')}
                        description={t('provider.requests.index.empty_description')}
                    />
                ) : (
                    <ul className="mt-6 space-y-3">
                        {requests.data.map((request) => {
                            const badge = request.match_level ? MATCH_BADGE[request.match_level] : undefined;

                            return (
                                <Card as="li" key={request.id}>
                                    <div className="flex items-center justify-between gap-2">
                                        <Link
                                            href={`/requests/${request.id}`}
                                            className="font-medium text-brand-600 underline hover:text-brand-700"
                                        >
                                            {request.title}
                                        </Link>
                                        {badge && <Badge variant={badge.variant}>{t(badge.labelKey)}</Badge>}
                                    </div>
                                    <p className="mt-1 text-sm text-gray-600">
                                        {request.category.name} · {request.area.name} · {t(URGENCY_KEYS[request.urgency])}
                                    </p>
                                    {request.photos.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {request.photos.map((photo) => (
                                                <img
                                                    key={photo.id}
                                                    src={photo.url}
                                                    alt=""
                                                    className="h-20 w-20 rounded border border-gray-200 object-cover"
                                                />
                                            ))}
                                        </div>
                                    )}
                                </Card>
                            );
                        })}
                    </ul>
                )}

                <PaginationNav links={requests.meta.links} />
            </div>
        </AppLayout>
    );
}
