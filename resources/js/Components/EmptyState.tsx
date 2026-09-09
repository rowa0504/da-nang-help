import { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/Button';

interface EmptyStateProps {
    message: ReactNode;
    actionLabel?: ReactNode;
    actionHref?: string;
    onAction?: () => void;
}

// A Link's <a> cannot contain a <button> without creating two nested
// focusable/interactive elements, so a link-triggered action is styled
// directly rather than wrapping the Button component.
const LINK_BUTTON_CLASSES = 'inline-flex rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700';

export function EmptyState({ message, actionLabel, actionHref, onAction }: EmptyStateProps) {
    return (
        <div className="rounded border border-dashed border-gray-300 p-8 text-center">
            <p className="text-gray-600">{message}</p>
            {actionLabel && actionHref && (
                <Link href={actionHref} className={`mt-4 ${LINK_BUTTON_CLASSES}`}>
                    {actionLabel}
                </Link>
            )}
            {actionLabel && !actionHref && onAction && (
                <Button type="button" variant="primary" size="compact" className="mt-4" onClick={onAction}>
                    {actionLabel}
                </Button>
            )}
        </div>
    );
}
