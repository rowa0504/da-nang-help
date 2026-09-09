import { ReactNode } from 'react';

export type AlertVariant = 'success' | 'warning' | 'danger' | 'info';

interface AlertProps {
    variant: AlertVariant;
    children: ReactNode;
    onDismiss?: () => void;
}

const VARIANT_CLASSES: Record<AlertVariant, string> = {
    success: 'border-green-300 bg-green-50 text-green-800',
    warning: 'border-yellow-300 bg-yellow-50 text-yellow-800',
    danger: 'border-red-300 bg-red-50 text-red-800',
    info: 'border-blue-300 bg-blue-50 text-blue-800',
};

export function Alert({ variant, children, onDismiss }: AlertProps) {
    return (
        <div role="status" className={['flex items-start justify-between gap-4 rounded border p-3 text-sm', VARIANT_CLASSES[variant]].join(' ')}>
            <div>{children}</div>
            {onDismiss && (
                <button type="button" onClick={onDismiss} className="shrink-0 text-current opacity-70 hover:opacity-100" aria-label="Dismiss">
                    ×
                </button>
            )}
        </div>
    );
}
