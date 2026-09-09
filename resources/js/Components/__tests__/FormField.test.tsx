import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';

describe('FormField', () => {
    it('injects id into the child, matching the label htmlFor', () => {
        render(
            <FormField label="Email" htmlFor="email">
                <Input type="email" />
            </FormField>,
        );
        const input = screen.getByLabelText('Email');
        expect(input).toHaveAttribute('id', 'email');
    });

    it('links only the hint via aria-describedby when there is no error', () => {
        render(
            <FormField label="Email" htmlFor="email" hint="We will never share this.">
                <Input type="email" />
            </FormField>,
        );
        const input = screen.getByLabelText('Email');
        const describedBy = input.getAttribute('aria-describedby');
        expect(describedBy).toBeTruthy();
        expect(screen.getByText('We will never share this.')).toHaveAttribute('id', describedBy!.trim());
        expect(input).toHaveAttribute('aria-invalid', 'false');
    });

    it('links both hint and error ids when both are present, and marks aria-invalid', () => {
        render(
            <FormField label="Email" htmlFor="email" hint="We will never share this." error="Email is required.">
                <Input type="email" />
            </FormField>,
        );
        const input = screen.getByLabelText('Email');
        const describedBy = input.getAttribute('aria-describedby')!.split(' ');
        expect(describedBy).toHaveLength(2);
        expect(screen.getByText('We will never share this.').id).toBe(describedBy[0]);
        expect(screen.getByText('Email is required.').id).toBe(describedBy[1]);
        expect(input).toHaveAttribute('aria-invalid', 'true');
    });
});
