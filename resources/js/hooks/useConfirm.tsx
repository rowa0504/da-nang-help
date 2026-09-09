import { ReactNode, useCallback, useEffect, useRef, useState } from 'react';
import { ButtonVariant } from '@/Components/Button';
import { Modal } from '@/Components/Modal';

interface ConfirmOptions {
    title: ReactNode;
    body: ReactNode;
    confirmVariant?: ButtonVariant;
    confirmLabel?: ReactNode;
}

// resolverRef (not state) owns the pending Promise's resolve function, so
// that both a second confirm() call and unmount can always settle whatever
// Promise is currently outstanding with `false`, exactly once, without ever
// calling setState() after unmount.
export function useConfirm() {
    const [state, setState] = useState<ConfirmOptions | null>(null);
    const resolverRef = useRef<((value: boolean) => void) | null>(null);

    const confirm = useCallback((options: ConfirmOptions) => {
        resolverRef.current?.(false);
        return new Promise<boolean>((resolve) => {
            resolverRef.current = resolve;
            setState(options);
        });
    }, []);

    const close = useCallback((result: boolean) => {
        const resolve = resolverRef.current;
        resolverRef.current = null;
        setState(null);
        resolve?.(result);
    }, []);

    useEffect(() => {
        return () => {
            resolverRef.current?.(false);
            resolverRef.current = null;
        };
    }, []);

    const confirmDialog: ReactNode = state && (
        <Modal
            open
            title={state.title}
            onClose={() => close(false)}
            onConfirm={() => close(true)}
            confirmVariant={state.confirmVariant}
            confirmLabel={state.confirmLabel}
        >
            {state.body}
        </Modal>
    );

    return { confirm, confirmDialog };
}
