import { ComponentPropsWithoutRef, ElementType } from 'react';

interface CardProps extends ComponentPropsWithoutRef<'div'> {
    as?: ElementType;
}

// Formalizes the `rounded border border-gray-200 p-4` pattern already used
// ad hoc across the app (offer/job list items, detail-page info panels).
export function Card({ as: Tag = 'div', className = '', ...rest }: CardProps) {
    return <Tag className={['rounded border border-gray-200 bg-white p-4', className].filter(Boolean).join(' ')} {...rest} />;
}
