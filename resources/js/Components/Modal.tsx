import { ReactNode, useEffect, useId, useRef } from 'react';
import { Button, ButtonVariant } from '@/Components/Button';
import { useTranslation } from '@/hooks/useTranslation';

interface ModalProps {
    open: boolean;
    title: ReactNode;
    children: ReactNode;
    onClose: () => void;
    confirmLabel?: ReactNode;
    cancelLabel?: ReactNode;
    onConfirm?: () => void;
    confirmVariant?: ButtonVariant;
    confirming?: boolean;
}

const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function Modal({
    open,
    title,
    children,
    onClose,
    confirmLabel,
    cancelLabel,
    onConfirm,
    confirmVariant = 'primary',
    confirming = false,
}: ModalProps) {
    const { t } = useTranslation();
    const panelRef = useRef<HTMLDivElement>(null);
    const previouslyFocusedRef = useRef<HTMLElement | null>(null);
    const titleId = useId();
    const bodyId = useId();

    // Runs the full open/close lifecycle (initial focus, focus trap, Escape,
    // scroll lock, focus restore) whenever `open` flips — this covers both a
    // Modal that's mounted only while open (useConfirm's confirmDialog) and
    // one that stays mounted with `open` toggling.
    useEffect(() => {
        if (!open) {
            return;
        }

        previouslyFocusedRef.current = document.activeElement as HTMLElement | null;

        const panel = panelRef.current;
        const focusable = panel ? Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)) : [];
        (focusable[0] ?? panel)?.focus();

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
                return;
            }

            if (event.key !== 'Tab' || !panel) {
                return;
            }

            const elements = Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR));
            if (elements.length === 0) {
                event.preventDefault();
                return;
            }

            const first = elements[0];
            const last = elements[elements.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = previousOverflow;
            previouslyFocusedRef.current?.focus();
        };
        // onClose is not included: call sites pass a fresh closure each
        // render, and re-running this effect on every render would re-steal
        // focus and re-lock scroll needlessly.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    if (!open) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            onClick={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <div
                ref={panelRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={bodyId}
                tabIndex={-1}
                className="w-full max-w-md rounded bg-white p-6 shadow-lg focus:outline-none"
            >
                <h2 id={titleId} className="text-lg font-semibold text-gray-900">
                    {title}
                </h2>
                <div id={bodyId} className="mt-2 text-sm text-gray-700">
                    {children}
                </div>
                <div className="mt-6 flex justify-end gap-2">
                    {onConfirm ? (
                        <>
                            <Button type="button" variant="secondary" onClick={onClose}>
                                {cancelLabel ?? t('common.cancel')}
                            </Button>
                            <Button type="button" variant={confirmVariant} loading={confirming} onClick={onConfirm}>
                                {confirmLabel ?? t('common.confirm')}
                            </Button>
                        </>
                    ) : (
                        <Button type="button" variant="secondary" onClick={onClose}>
                            {t('common.close')}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
