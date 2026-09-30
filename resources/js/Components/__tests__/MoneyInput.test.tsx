import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MoneyInput } from '@/Components/MoneyInput';

function setup(overrides: Partial<Parameters<typeof MoneyInput>[0]> = {}) {
    const onChange = vi.fn();
    const utils = render(<MoneyInput value="" onChange={onChange} locale="en" id="price" {...overrides} />);
    return { onChange, input: () => screen.getByRole('textbox') as HTMLInputElement, ...utils };
}

function paste(el: HTMLElement, text: string) {
    fireEvent.paste(el, { clipboardData: { getData: () => text } });
}

describe('MoneyInput basic attributes', () => {
    it('renders a type="text" input with inputMode="numeric"', () => {
        setup();
        const el = screen.getByRole('textbox');
        expect(el).toHaveAttribute('type', 'text');
        expect(el).toHaveAttribute('inputMode', 'numeric');
    });

    it('passes through id, aria-invalid, and aria-describedby (the props FormField injects)', () => {
        setup({ id: 'price', 'aria-invalid': true, 'aria-describedby': 'price-error' } as never);
        const el = screen.getByRole('textbox');
        expect(el).toHaveAttribute('id', 'price');
        expect(el).toHaveAttribute('aria-invalid', 'true');
        expect(el).toHaveAttribute('aria-describedby', 'price-error');
    });
});

describe('MoneyInput display grouping', () => {
    it('shows the committed value grouped by thousands for the given locale', () => {
        setup({ value: '450000', locale: 'en' });
        expect(screen.getByRole('textbox')).toHaveValue('450,000');
    });

    it('groups with a period for vi locale', () => {
        setup({ value: '450000', locale: 'vi' });
        expect(screen.getByRole('textbox')).toHaveValue('450.000');
    });

    it('shows an empty field as an empty string, not "0"', () => {
        setup({ value: '' });
        expect(screen.getByRole('textbox')).toHaveValue('');
    });

    it('displays and can submit a 10-digit (decimal(12,2) integer-part max) value', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '9999999999' } });
        expect(input()).toHaveValue('9,999,999,999');
        expect(onChange).toHaveBeenCalledWith('9999999999');
    });
});

describe('MoneyInput typed input — plain digits and full-width', () => {
    it('accepts plain digit input and reports the ASCII value via onChange', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '450000' } });
        expect(onChange).toHaveBeenCalledWith('450000');
    });

    it('converts full-width digits typed directly to ASCII', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '１２３' } });
        expect(onChange).toHaveBeenCalledWith('123');
    });

    it('accepts clearing the field entirely (e.g. select-all + Delete), not rejecting it as an invalid amount', () => {
        const { onChange, input } = setup({ value: '450000' });
        fireEvent.change(input(), { target: { value: '' } });
        expect(onChange).toHaveBeenCalledWith('');
        expect(input()).toHaveValue('');
    });

    it('does not call onChange and leaves the display unchanged for a letter keystroke', () => {
        const { onChange, input } = setup({ value: '100' });
        fireEvent.change(input(), { target: { value: '100a' } });
        expect(onChange).not.toHaveBeenCalled();
        expect(input()).toHaveValue('100');
    });

    it('does not silently strip letters out of a mixed value (100abc000 is rejected, not coerced to 100000)', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '100abc000' } });
        expect(onChange).not.toHaveBeenCalled();
        expect(input()).toHaveValue('');
    });
});

describe('MoneyInput typed input — grouped values entered in one shot', () => {
    it('accepts a correct comma thousands grouping', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '100,000' } });
        expect(onChange).toHaveBeenCalledWith('100000');
    });

    it('accepts a correct period thousands grouping', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '100.000' } });
        expect(onChange).toHaveBeenCalledWith('100000');
    });

    it('accepts multiple repeated groups', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '1,000,000' } });
        expect(onChange).toHaveBeenCalledWith('1000000');
    });

    it('rejects an incomplete 2-digit final group rather than silently reinterpreting it as a different amount', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '100.00' } });
        expect(onChange).not.toHaveBeenCalled();
        expect(input()).toHaveValue('');
    });

    it('rejects "1.5" and "12.34" the same way', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '1.5' } });
        expect(onChange).not.toHaveBeenCalled();
        fireEvent.change(input(), { target: { value: '12.34' } });
        expect(onChange).not.toHaveBeenCalled();
    });

    it('rejects comma and period mixed together in the same value', () => {
        const { onChange, input } = setup({ value: '' });
        fireEvent.change(input(), { target: { value: '1,000.000' } });
        expect(onChange).not.toHaveBeenCalled();
        expect(input()).toHaveValue('');
    });
});

describe('MoneyInput mid-edit typing on an already-grouped display', () => {
    it('appending a digit after the display has already been grouped extends the number correctly', () => {
        const { onChange, input } = setup({ value: '450000', locale: 'en' });
        // The DOM already shows "450,000"; simulate the browser having
        // inserted one more "1" at the end before onChange fires, exactly
        // as a real keystroke would.
        fireEvent.change(input(), { target: { value: '450,0001' } });
        expect(onChange).toHaveBeenCalledWith('4500001');
    });

    it('inserting a digit in the middle of an already-grouped display updates the correct position', () => {
        const { onChange, input } = setup({ value: '450000', locale: 'en' });
        // User placed the caret right after "45" in "450,000" and typed "9".
        fireEvent.change(input(), { target: { value: '4590,000' } });
        expect(onChange).toHaveBeenCalledWith('4590000');
    });

    it('Backspace removing the last digit of an already-grouped display shrinks the number correctly', () => {
        const { onChange, input } = setup({ value: '450000', locale: 'en' });
        fireEvent.change(input(), { target: { value: '450,00' } });
        expect(onChange).toHaveBeenCalledWith('45000');
    });

    it('Delete removing a digit from the middle of an already-grouped display updates correctly', () => {
        // Distinct digits (not all "0") so the removed position is verifiable:
        // "123,456" with the "3" deleted (Delete pressed right before it).
        const { onChange, input } = setup({ value: '123456', locale: 'en' });
        fireEvent.change(input(), { target: { value: '12,456' } });
        expect(onChange).toHaveBeenCalledWith('12456');
    });

    it('removing just the auto-inserted separator character leaves the numeric value unchanged', () => {
        const { onChange, input } = setup({ value: '450000', locale: 'en' });
        // The display was "450,000"; simulate Backspace over just the comma.
        fireEvent.change(input(), { target: { value: '450000' } });
        // The resolved value equals the value already held, so onChange —
        // which only fires on an actual change — is not called at all.
        expect(onChange).not.toHaveBeenCalled();
        expect(input()).toHaveValue('450,000');
    });
});

describe('MoneyInput paste handling', () => {
    it('normalizes a paste containing only digits, full-width digits, and a consistent separator', () => {
        const { onChange, input } = setup({ value: '' });
        paste(input(), '1,000');
        expect(onChange).toHaveBeenCalledWith('1000');
    });

    it('rejects a paste containing letters outright, leaving the current value unchanged', () => {
        const { onChange, input } = setup({ value: '100' });
        paste(input(), '100abc000');
        expect(onChange).not.toHaveBeenCalled();
        expect(input()).toHaveValue('100');
    });

    it('rejects an ambiguous decimal-looking paste ("100.00"), leaving the current value unchanged', () => {
        const { onChange, input } = setup({ value: '100' });
        paste(input(), '100.00');
        expect(onChange).not.toHaveBeenCalled();
        expect(input()).toHaveValue('100');
    });

    it('rejects a paste mixing comma and period, leaving the current value unchanged', () => {
        const { onChange, input } = setup({ value: '100' });
        paste(input(), '1,000.000');
        expect(onChange).not.toHaveBeenCalled();
        expect(input()).toHaveValue('100');
    });

    it('validates the pasted text as a whole, not spliced together with the surrounding grouped display', () => {
        // Pasting "000" into the middle of an existing, already-grouped
        // "450,000" must not be re-validated together with the
        // surrounding "450,...,000" — only "000" itself is checked.
        const { onChange, input } = setup({ value: '450000', locale: 'en' });
        const el = input();
        el.setSelectionRange(4, 4); // right after "450," in "450,000"
        paste(el, '000');
        expect(onChange).toHaveBeenCalledWith('450000000');
    });
});
