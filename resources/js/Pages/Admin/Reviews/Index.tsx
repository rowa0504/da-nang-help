import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminReviewData, PaginatedData } from '@/types';

interface Props {
    reviews: PaginatedData<AdminReviewData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const dangerButtonClass =
    'rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50';

export default function Index({ reviews }: Props) {
    const [processingId, setProcessingId] = useState<number | null>(null);

    function hide(review: AdminReviewData) {
        if (!confirm(`Hide this review from ${review.rater_name} about ${review.ratee_name}?`)) {
            return;
        }
        setProcessingId(review.id);
        router.patch(`/admin/reviews/${review.id}/hide`, {}, { onFinish: () => setProcessingId(null) });
    }

    return (
        <main className="mx-auto max-w-4xl p-6 font-sans">
            <Head title="Reviews" />
            <h1 className="text-2xl font-semibold text-gray-900">Reviews</h1>

            {reviews.data.length === 0 ? (
                <p className="mt-4 text-gray-600">No reviews yet.</p>
            ) : (
                <table className="mt-4 w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th className="border-b border-gray-300 py-2 text-left">Rating</th>
                            <th className="border-b border-gray-300 py-2 text-left">Comment</th>
                            <th className="border-b border-gray-300 py-2 text-left">From</th>
                            <th className="border-b border-gray-300 py-2 text-left">About</th>
                            <th className="border-b border-gray-300 py-2 text-left">Status</th>
                            <th className="border-b border-gray-300 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {reviews.data.map((review) => (
                            <tr key={review.id}>
                                <td className="border-b border-gray-100 py-2">{review.rating} / 5</td>
                                <td className="border-b border-gray-100 py-2">{review.comment ?? '—'}</td>
                                <td className="border-b border-gray-100 py-2">{review.rater_name}</td>
                                <td className="border-b border-gray-100 py-2">{review.ratee_name}</td>
                                <td className="border-b border-gray-100 py-2">{review.is_hidden ? 'Hidden' : 'Visible'}</td>
                                <td className="border-b border-gray-100 py-2">
                                    {!review.is_hidden && (
                                        <button
                                            onClick={() => hide(review)}
                                            disabled={processingId === review.id}
                                            className={dangerButtonClass}
                                        >
                                            Hide
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
                    Back to dashboard
                </Link>
            </p>
        </main>
    );
}

function PaginationNav({ links }: { links: { url: string | null; label: string; active: boolean }[] }) {
    return (
        <nav className="mt-4 flex flex-wrap gap-2 text-sm">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={link.active ? `${linkClass} font-bold` : linkClass}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span key={index} className="text-gray-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                ),
            )}
        </nav>
    );
}
