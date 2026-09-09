import { ReactNode } from 'react';

interface PageHeaderProps {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
}

export function PageHeader({ title, description, actions }: PageHeaderProps) {
    return (
        <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h1 className="text-2xl font-semibold text-gray-900">{title}</h1>
                {description && <p className="mt-1 text-sm text-gray-600">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
    );
}
