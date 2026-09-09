import { KeyboardEvent, useRef } from 'react';
import { useTranslation } from '@/hooks/useTranslation';

interface StarRatingProps {
    value: number;
    onChange?: (value: number) => void;
    readOnly?: boolean;
    size?: 'sm' | 'md';
    ariaLabel?: string;
    // Injected by FormField's cloneElement() when StarRating is used as a
    // FormField child — forwarded onto the radiogroup container.
    id?: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
}

const STARS = [1, 2, 3, 4, 5];

export function StarRating({
    value,
    onChange,
    readOnly,
    size = 'md',
    ariaLabel,
    id,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}: StarRatingProps) {
    const { t } = useTranslation();
    const interactive = !readOnly && !!onChange;
    const starRefs = useRef<(HTMLButtonElement | null)[]>([]);
    const sizeClass = size === 'sm' ? 'text-base' : 'text-xl';

    if (!interactive) {
        return (
            <span>
                <span aria-hidden="true" className={sizeClass}>
                    {'★'.repeat(value)}
                    {'☆'.repeat(5 - value)}
                </span>
                <span className="sr-only">{t('jobs.show.star_count', { count: value })}</span>
            </span>
        );
    }

    function select(next: number) {
        const clamped = Math.min(5, Math.max(1, next));
        onChange?.(clamped);
        starRefs.current[clamped - 1]?.focus();
    }

    // ArrowUp/Down/Home/End would otherwise scroll the page — every handled
    // key calls preventDefault().
    function handleKeyDown(event: KeyboardEvent<HTMLButtonElement>) {
        switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                event.preventDefault();
                select(value + 1);
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                event.preventDefault();
                select(value - 1);
                break;
            case 'Home':
                event.preventDefault();
                select(1);
                break;
            case 'End':
                event.preventDefault();
                select(5);
                break;
        }
    }

    return (
        <div
            id={id}
            role="radiogroup"
            aria-label={ariaLabel}
            aria-invalid={ariaInvalid}
            aria-describedby={ariaDescribedBy}
            className="flex gap-1"
        >
            {STARS.map((n) => (
                <button
                    key={n}
                    type="button"
                    ref={(el) => {
                        starRefs.current[n - 1] = el;
                    }}
                    role="radio"
                    aria-checked={value === n}
                    aria-label={t('jobs.show.star_count', { count: n })}
                    tabIndex={value === n ? 0 : -1}
                    onClick={() => select(n)}
                    onKeyDown={handleKeyDown}
                    className={`${sizeClass} rounded leading-none text-yellow-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1`}
                >
                    {n <= value ? '★' : '☆'}
                </button>
            ))}
        </div>
    );
}
