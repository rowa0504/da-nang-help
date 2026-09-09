import { Fragment, Key, ReactNode } from 'react';
import { Card } from '@/Components/Card';

export interface DataTableColumn<T> {
    key: string;
    header: ReactNode;
    render: (row: T) => ReactNode;
    className?: string;
}

interface DataTableProps<T> {
    columns: DataTableColumn<T>[];
    rows: T[];
    rowKey: (row: T) => Key;
    emptyState: ReactNode;
}

// Desktop and mobile are two parallel renders driven by the same `columns`
// array, so the two views can never drift out of sync — only the wrapper
// markup differs, gated by Tailwind's responsive utilities (no JS media
// query logic).
export function DataTable<T>({ columns, rows, rowKey, emptyState }: DataTableProps<T>) {
    if (rows.length === 0) {
        return <>{emptyState}</>;
    }

    return (
        <Fragment>
            <table className="hidden w-full border-collapse text-sm md:table">
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} className={['border-b border-gray-300 py-2 text-left', column.className].filter(Boolean).join(' ')}>
                                {column.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={rowKey(row)}>
                            {columns.map((column) => (
                                <td key={column.key} className={['border-b border-gray-100 py-2', column.className].filter(Boolean).join(' ')}>
                                    {column.render(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>

            <ul className="space-y-3 md:hidden">
                {rows.map((row) => (
                    <Card as="li" key={rowKey(row)}>
                        <dl className="space-y-2">
                            {columns.map((column) => (
                                <div key={column.key} className="flex items-start justify-between gap-4 text-sm">
                                    <dt className="text-gray-500">{column.header}</dt>
                                    <dd className="text-right text-gray-800">{column.render(row)}</dd>
                                </div>
                            ))}
                        </dl>
                    </Card>
                ))}
            </ul>
        </Fragment>
    );
}
