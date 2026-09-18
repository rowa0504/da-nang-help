import { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { Button, buttonClasses } from '@/Components/Button';

interface EmptyStateProps {
    icon?: ReactNode;
    title: ReactNode;
    description?: ReactNode;
    actionLabel?: ReactNode;
    actionHref?: string;
    onAction?: () => void;
    // Explains why no action is offered here (e.g. a permission the
    // current viewer doesn't have), rather than leaving an empty list with
    // no next step at all.
    note?: ReactNode;
}

export function EmptyState({ icon, title, description, actionLabel, actionHref, onAction, note }: EmptyStateProps) {
    return (
        <div className="rounded border border-dashed border-gray-300 p-8 text-center">
            {icon && <div className="mx-auto mb-3 flex h-10 w-10 items-center justify-center text-gray-400">{icon}</div>}
            <p className="font-medium text-gray-900">{title}</p>
            {description && <p className="mt-1 text-sm text-gray-600">{description}</p>}
            {actionLabel && actionHref && (
                <Link href={actionHref} className={`mt-4 ${buttonClasses('primary', 'compact')}`}>
                    {actionLabel}
                </Link>
            )}
            {actionLabel && !actionHref && onAction && (
                <Button type="button" variant="primary" size="compact" className="mt-4" onClick={onAction}>
                    {actionLabel}
                </Button>
            )}
            {note && <p className="mt-3 text-xs text-gray-500">{note}</p>}
        </div>
    );
}
