import { Children, cloneElement, ReactElement, ReactNode, useId } from 'react';

interface FormFieldProps {
    label: ReactNode;
    htmlFor: string;
    error?: string;
    hint?: ReactNode;
    required?: boolean;
    children: ReactElement;
}

// FormField composes with a single input-like child (Input/Textarea/Select/
// StarRating/...) rather than owning an input itself, so it injects
// id/aria-invalid/aria-describedby into that child via cloneElement().
// Children.only() enforces the single-child contract at runtime, not just
// in the type signature.
export function FormField({ label, htmlFor, error, hint, required, children }: FormFieldProps) {
    const hintId = useId();
    const errorId = useId();
    const describedBy = [hint ? hintId : null, error ? errorId : null].filter(Boolean).join(' ') || undefined;

    const child = Children.only(children);
    const field = cloneElement(child, {
        id: htmlFor,
        'aria-invalid': !!error,
        'aria-describedby': describedBy,
    } as Record<string, unknown>);

    return (
        <div>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-gray-700">
                {label}
                {required && <span className="text-red-600"> *</span>}
            </label>
            {field}
            {hint && (
                <p id={hintId} className="mt-1 text-sm text-gray-500">
                    {hint}
                </p>
            )}
            {error && (
                <p id={errorId} className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}
