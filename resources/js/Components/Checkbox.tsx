import { ComponentPropsWithoutRef, ReactNode } from 'react';

interface CheckboxProps extends Omit<ComponentPropsWithoutRef<'input'>, 'type'> {
    label: ReactNode;
}

// Checkboxes sit beside their label rather than above it, so — unlike
// Input/Textarea/Select — this component owns its own <label> instead of
// relying on FormField to supply one.
export function Checkbox({ label, className = '', ...rest }: CheckboxProps) {
    return (
        <label className={['flex items-center gap-2 text-sm text-gray-800', className].filter(Boolean).join(' ')}>
            <input type="checkbox" className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" {...rest} />
            {label}
        </label>
    );
}
