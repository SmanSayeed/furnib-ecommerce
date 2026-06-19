import type { ReactNode } from 'react';

export type Column<T> = {
    /** Unique key; also used to read `row[key]` when no `cell` is given. */
    key: string;
    header: string;
    /** Custom cell renderer. Defaults to `row[key]`. */
    cell?: (row: T) => ReactNode;
    align?: 'left' | 'right';
    /** Extra classes for both the th and td of this column. */
    className?: string;
    /** Hide the label in the auto mobile-card layout (e.g. for an actions column). */
    hideLabelOnMobile?: boolean;
};

type Props<T> = {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    /**
     * Optional custom mobile card. When omitted, each row renders as a stacked
     * label/value list built from the same `columns` — keeping desktop and
     * mobile in sync (per docs/admin-ui/RESPONSIVE-UI-GUIDE.md).
     */
    renderMobileCard?: (row: T) => ReactNode;
    className?: string;
};

/**
 * Responsive data table: a real `<table>` on md+ screens, automatically
 * collapsing to stacked cards on mobile so rows never overflow horizontally.
 */
export function DataTable<T>({
    columns,
    rows,
    rowKey,
    renderMobileCard,
    className,
}: Props<T>) {
    const value = (col: Column<T>, row: T): ReactNode =>
        col.cell ? col.cell(row) : ((row as Record<string, unknown>)[col.key] as ReactNode);

    return (
        <div className={`rounded-xl border bg-card ${className ?? ''}`}>
            <table className="hidden w-full text-sm md:table">
                <thead>
                    <tr className="border-b text-left text-xs text-muted-foreground">
                        {columns.map((c) => (
                            <th
                                key={c.key}
                                className={`px-4 py-2 font-normal ${c.align === 'right' ? 'text-right' : ''} ${c.className ?? ''}`}
                            >
                                {c.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={rowKey(row)} className="border-b last:border-0">
                            {columns.map((c) => (
                                <td
                                    key={c.key}
                                    className={`px-4 py-3 ${c.align === 'right' ? 'text-right' : ''} ${c.className ?? ''}`}
                                >
                                    {value(c, row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>

            <div className="divide-y md:hidden">
                {rows.map((row) => (
                    <div key={rowKey(row)} className="p-4">
                        {renderMobileCard ? (
                            renderMobileCard(row)
                        ) : (
                            <dl className="grid gap-1.5">
                                {columns.map((c) => (
                                    <div
                                        key={c.key}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        {!c.hideLabelOnMobile && (
                                            <dt className="text-xs text-muted-foreground">
                                                {c.header}
                                            </dt>
                                        )}
                                        <dd className="text-sm">{value(c, row)}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
