import { ComponentPropsWithoutRef } from 'react';

interface SelectProps extends ComponentPropsWithoutRef<'select'> {
    invalid?: boolean;
}

const BASE = 'mt-1 block w-full rounded border px-3 py-2 focus:outline-none focus:ring-1';
const VALID = 'border-gray-300 focus:border-blue-500 focus:ring-blue-500';
const INVALID = 'border-red-400 focus:border-red-500 focus:ring-red-500';

export function Select({ invalid = false, className = '', children, ...rest }: SelectProps) {
    return (
        <select className={[BASE, invalid ? INVALID : VALID, className].filter(Boolean).join(' ')} {...rest}>
            {children}
        </select>
    );
}
