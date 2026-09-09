import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DataTable } from '@/Components/DataTable';

interface Row {
    id: number;
    name: string;
}

const rows: Row[] = [
    { id: 1, name: 'Alice' },
    { id: 2, name: 'Bob' },
];

const columns = [{ key: 'name', header: 'Name', render: (row: Row) => row.name }];

describe('DataTable', () => {
    it('renders a desktop table and a mobile card list from the same data', () => {
        const { container } = render(<DataTable columns={columns} rows={rows} rowKey={(row) => row.id} emptyState={<p>None</p>} />);

        const table = container.querySelector('table');
        expect(table).not.toBeNull();
        expect(table).toHaveClass('hidden', 'md:table');

        const list = container.querySelector('ul');
        expect(list).not.toBeNull();
        expect(list).toHaveClass('md:hidden');

        // Both renders carry the same row data.
        expect(screen.getAllByText('Alice')).toHaveLength(2);
        expect(screen.getAllByText('Bob')).toHaveLength(2);
    });

    it('renders the emptyState instead of any table markup when rows is empty', () => {
        const { container } = render(<DataTable columns={columns} rows={[]} rowKey={(row: Row) => row.id} emptyState={<p>No rows yet.</p>} />);

        expect(screen.getByText('No rows yet.')).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
        expect(container.querySelector('ul')).toBeNull();
    });
});
