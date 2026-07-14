import { ChevronDown, ChevronsUpDown, ChevronUp } from 'lucide-react';
import type { ReactNode } from 'react';

export type SortDir = 'asc' | 'desc';

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
    /**
     * Server-side sort key. When set together with the table's `onSort`, this
     * column header becomes a sort toggle. Must match the backend sort
     * whitelist (e.g. `total`, `created_at`).
     */
    sortKey?: string;
};

/**
 * Row-selection wiring for bulk actions. When present, the table renders a
 * leading checkbox column (header = select/clear all rows on the page). The
 * parent owns the selected-key set so selection can persist across pages.
 */
export type TableSelection = {
    selected: Set<string | number>;
    onToggle: (key: string | number) => void;
    /** Select every row on the current page, or clear them if all are selected. */
    onToggleAll: (keys: Array<string | number>) => void;
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
    /**
     * Minimum table width on md+ (any Tailwind width class, e.g. `min-w-[72rem]`).
     * Below this the table scrolls horizontally inside its own container instead of
     * spilling its last columns past the viewport. Set it on wide tables.
     */
    minWidth?: string;
    /** Active sort column key (matches a column's `sortKey`). */
    sort?: string;
    /** Active sort direction. */
    dir?: SortDir;
    /** Called with a column's `sortKey` when its header is clicked. */
    onSort?: (sortKey: string) => void;
    /** Enable the leading checkbox column for bulk selection. */
    selection?: TableSelection;
};

function SortIcon({ active, dir }: { active: boolean; dir?: SortDir }) {
    if (!active) {
        return <ChevronsUpDown className="size-3.5 opacity-50" />;
    }

    return dir === 'asc' ? (
        <ChevronUp className="size-3.5" />
    ) : (
        <ChevronDown className="size-3.5" />
    );
}

/**
 * Responsive data table: a real `<table>` on md+ screens, automatically
 * collapsing to stacked cards on mobile so rows never overflow horizontally.
 * Columns with a `sortKey` render a clickable, server-driven sort header.
 */
export function DataTable<T>({
    columns,
    rows,
    rowKey,
    renderMobileCard,
    className,
    minWidth,
    sort,
    dir,
    onSort,
    selection,
}: Props<T>) {
    const value = (col: Column<T>, row: T): ReactNode =>
        col.cell ? col.cell(row) : ((row as Record<string, unknown>)[col.key] as ReactNode);

    const pageKeys = rows.map(rowKey);
    const allOnPageSelected =
        pageKeys.length > 0 && pageKeys.every((k) => selection?.selected.has(k));

    return (
        <div className={`overflow-hidden rounded-xl border bg-card ${className ?? ''}`}>
            {/* The table scrolls inside this box. Without it, a wide table (many
                columns, or inline controls with a minimum width) simply spills its
                last columns — Actions included — past the right edge of the page,
                with no scrollbar to reach them. */}
            <div className="hidden overflow-x-auto md:block">
            <table className={`w-full text-sm ${minWidth ?? ''}`}>
                <thead>
                    <tr className="border-b text-left text-xs text-muted-foreground">
                        {selection && (
                            <th className="w-10 px-4 py-2">
                                <input
                                    type="checkbox"
                                    aria-label="Select all rows on this page"
                                    className="size-4 cursor-pointer rounded border-input align-middle"
                                    checked={allOnPageSelected}
                                    onChange={() => selection.onToggleAll(pageKeys)}
                                />
                            </th>
                        )}
                        {columns.map((c) => {
                            const sortable = Boolean(c.sortKey && onSort);

                            return (
                                <th
                                    key={c.key}
                                    aria-sort={
                                        sortable && sort === c.sortKey
                                            ? dir === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : undefined
                                    }
                                    className={`px-4 py-2 font-normal ${c.align === 'right' ? 'text-right' : ''} ${c.className ?? ''}`}
                                >
                                    {sortable ? (
                                        <button
                                            type="button"
                                            onClick={() => onSort?.(c.sortKey as string)}
                                            className={`inline-flex items-center gap-1 transition-colors hover:text-foreground ${c.align === 'right' ? 'flex-row-reverse' : ''}`}
                                        >
                                            {c.header}
                                            <SortIcon active={sort === c.sortKey} dir={dir} />
                                        </button>
                                    ) : (
                                        c.header
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => {
                        const key = rowKey(row);

                        return (
                        <tr key={key} className="border-b last:border-0">
                            {selection && (
                                <td className="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        aria-label="Select row"
                                        className="size-4 cursor-pointer rounded border-input align-middle"
                                        checked={selection.selected.has(key)}
                                        onChange={() => selection.onToggle(key)}
                                    />
                                </td>
                            )}
                            {columns.map((c) => (
                                <td
                                    key={c.key}
                                    className={`px-4 py-3 ${c.align === 'right' ? 'text-right' : ''} ${c.className ?? ''}`}
                                >
                                    {value(c, row)}
                                </td>
                            ))}
                        </tr>
                        );
                    })}
                </tbody>
            </table>
            </div>

            <div className="divide-y md:hidden">
                {rows.map((row) => {
                    const key = rowKey(row);

                    return (
                    <div key={key} className="flex items-start gap-3 p-4">
                        {selection && (
                            <input
                                type="checkbox"
                                aria-label="Select row"
                                className="mt-1 size-4 shrink-0 cursor-pointer rounded border-input"
                                checked={selection.selected.has(key)}
                                onChange={() => selection.onToggle(key)}
                            />
                        )}
                        <div className="min-w-0 flex-1">
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
                    </div>
                    );
                })}
            </div>
        </div>
    );
}
