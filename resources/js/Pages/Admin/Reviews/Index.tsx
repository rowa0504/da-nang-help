import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminReviewData, PaginatedData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PaginationNav } from '@/Components/PaginationNav';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    reviews: PaginatedData<AdminReviewData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const dangerButtonClass =
    'rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50';

export default function Index({ reviews }: Props) {
    const { t } = useTranslation();
    const [processingId, setProcessingId] = useState<number | null>(null);

    function hide(review: AdminReviewData) {
        if (!confirm(t('admin.reviews.index.confirm_hide', { rater: review.rater_name, ratee: review.ratee_name }))) {
            return;
        }
        setProcessingId(review.id);
        router.patch(`/admin/reviews/${review.id}/hide`, {}, { onFinish: () => setProcessingId(null) });
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-4xl p-6 font-sans">
                <Head title={t('admin.reviews.index.title')} />
                <h1 className="text-2xl font-semibold text-gray-900">{t('admin.reviews.index.title')}</h1>

                {reviews.data.length === 0 ? (
                    <p className="mt-4 text-gray-600">{t('admin.reviews.index.empty')}</p>
                ) : (
                    <table className="mt-4 w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.rating')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.comment')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('admin.reviews.index.from')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('admin.reviews.index.about')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.status')}</th>
                                <th className="border-b border-gray-300 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {reviews.data.map((review) => (
                                <tr key={review.id}>
                                    <td className="border-b border-gray-100 py-2">{t('admin.reviews.index.rating_out_of_5', { rating: review.rating })}</td>
                                    <td className="border-b border-gray-100 py-2">{review.comment ?? t('common.none')}</td>
                                    <td className="border-b border-gray-100 py-2">{review.rater_name}</td>
                                    <td className="border-b border-gray-100 py-2">{review.ratee_name}</td>
                                    <td className="border-b border-gray-100 py-2">
                                        {review.is_hidden ? t('status.review.hidden') : t('status.review.visible')}
                                    </td>
                                    <td className="border-b border-gray-100 py-2">
                                        {!review.is_hidden && (
                                            <button
                                                onClick={() => hide(review)}
                                                disabled={processingId === review.id}
                                                className={dangerButtonClass}
                                            >
                                                {t('common.hide')}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <PaginationNav links={reviews.meta.links} />

                <p className="mt-4">
                    <Link href="/dashboard" className={linkClass}>
                        {t('nav.back_to_dashboard')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
