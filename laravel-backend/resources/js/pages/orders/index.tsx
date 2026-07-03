import { Head, Link, router } from '@inertiajs/react';
import { Eye, FileText, ShoppingCart, Ticket, X } from 'lucide-react';
import { useRef, useState } from 'react';
import {  DataTable } from '@/components/admin/data-table';
import type {Column, SortDir} from '@/components/admin/data-table';
import { DateRangeFilter } from '@/components/admin/date-range-filter';
import type { DatePreset } from '@/components/admin/date-range-filter';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type OrderRow = {
    id: number;
    order_no: string;
    customer: string | null;
    mobile: string | null;
    total: string;
    status: string;
    pending_reason: string | null;
    payment_status: string;
    created_at: string | null;
};

const PENDING_REASON_LABELS: Record<string, string> = {
    new_order: 'New order',
    call_waiting: 'Call waiting',
    payment_pending: 'Payment pending',
    need_expert_call: 'Need expert call',
    other: 'Other',
};

type Filters = {
    search: string;
    status: string;
    payment_status: string;
    sort: string;
    dir: SortDir;
    range: DatePreset;
    from: string;
    to: string;
    per_page: string;
};

type Props = {
    orders: OrderRow[];
    meta: { current_page: number; last_page: number; total: number };
    filters: Filters;
    statuses: string[];
    paymentStatuses: string[];
    transitions: Record<string, string[]>;
};

const PER_PAGE_OPTIONS = ['20', '50', '100'];

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    confirmed: 'bg-blue-500/15 text-blue-600 dark:text-blue-400',
    processing: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
    shipped: 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-400',
    delivered: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    cancelled: 'bg-muted text-muted-foreground',
    returned: 'bg-red-500/15 text-red-600 dark:text-red-400',
};

function StatusBadge({ status }: { status: string }) {
    return (
        <span
            className={`inline-block rounded-md px-2 py-0.5 text-xs font-medium capitalize ${STATUS_STYLES[status] ?? 'bg-muted text-muted-foreground'}`}
        >
            {status}
        </span>
    );
}

function OrderBulkBar({
    count,
    statuses,
    onStatus,
    onDownload,
    onClear,
}: {
    count: number;
    statuses: string[];
    onStatus: (status: string) => void;
    onDownload: (kind: 'invoices' | 'labels') => void;
    onClear: () => void;
}) {
    const [status, setStatus] = useState('');
    const inputClass = 'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

    return (
        <div className="sticky top-4 z-20 mb-3 flex flex-col gap-3 rounded-xl border bg-card p-3 shadow-lg sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2 text-sm">
                <span className="font-medium">{count} selected</span>
                <Button variant="ghost" size="sm" onClick={onClear} aria-label="Clear selection">
                    <X className="size-4" /> Clear
                </Button>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <select
                    aria-label="Bulk status"
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                    className={inputClass}
                >
                    <option value="">Set status…</option>
                    {statuses.map((s) => (
                        <option key={s} value={s} className="capitalize">
                            {s}
                        </option>
                    ))}
                </select>
                <Button onClick={() => status && onStatus(status)} disabled={!status}>
                    Apply
                </Button>

                <span className="mx-1 hidden h-6 w-px bg-border sm:block" />

                <Button variant="outline" onClick={() => onDownload('invoices')}>
                    <FileText className="size-4" /> Invoices
                </Button>
                <Button variant="outline" onClick={() => onDownload('labels')}>
                    <Ticket className="size-4" /> Shipping labels
                </Button>
            </div>
        </div>
    );
}

function RowStatus({ row, transitions }: { row: OrderRow; transitions: Record<string, string[]> }) {
    const options = transitions[row.status] ?? [];
    const [next, setNext] = useState('');
    const [saving, setSaving] = useState(false);
    const inputClass = 'h-8 rounded-md border border-input bg-transparent px-2 text-xs shadow-xs';

    const save = () => {
        if (!next) {
            return;
        }

        setSaving(true);
        router.put(
            `/admin/orders/${row.id}/status`,
            { status: next },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    setNext('');
                },
            },
        );
    };

    return (
        <div className="flex flex-col items-start gap-1">
            <StatusBadge status={row.status} />
            {row.pending_reason && (
                <span className="text-xs text-muted-foreground">
                    {PENDING_REASON_LABELS[row.pending_reason] ?? row.pending_reason}
                </span>
            )}
            {options.length > 0 && (
                <div className="mt-0.5 flex items-center gap-1">
                    <select
                        aria-label={`Change status for ${row.order_no}`}
                        value={next}
                        onChange={(e) => setNext(e.target.value)}
                        className={`${inputClass} capitalize`}
                    >
                        <option value="">Change…</option>
                        {options.map((s) => (
                            <option key={s} value={s} className="capitalize">
                                {s}
                            </option>
                        ))}
                    </select>
                    <Button size="sm" className="h-8 px-2 text-xs" disabled={!next || saving} onClick={save}>
                        Save
                    </Button>
                </div>
            )}
        </div>
    );
}

function RowActions({ row }: { row: OrderRow }) {
    return (
        <div className="flex justify-end gap-0.5">
            <Button variant="ghost" size="icon" asChild aria-label="View order">
                <Link href={`/admin/orders/${row.id}`}>
                    <Eye className="size-4" />
                </Link>
            </Button>
            <Button variant="ghost" size="icon" asChild aria-label="Download invoice">
                <a href={`/admin/orders/${row.id}/invoice`} target="_blank" rel="noopener noreferrer">
                    <FileText className="size-4" />
                </a>
            </Button>
            <Button variant="ghost" size="icon" asChild aria-label="Download shipping label">
                <a href={`/admin/orders/${row.id}/label`} target="_blank" rel="noopener noreferrer">
                    <Ticket className="size-4" />
                </a>
            </Button>
        </div>
    );
}

export default function OrdersIndex({ orders, meta, filters, statuses, paymentStatuses, transitions }: Props) {
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [selected, setSelected] = useState<Set<string | number>>(new Set());
    const [allMatching, setAllMatching] = useState(false);

    const clearSelection = () => {
        setSelected(new Set());
        setAllMatching(false);
    };

    const buildParams = (extra: Filters): Record<string, string> => {
        const params: Record<string, string> = {};

        if (extra.search) {
params.search = extra.search;
}

        if (extra.status) {
params.status = extra.status;
}

        if (extra.payment_status) {
params.payment_status = extra.payment_status;
}

        if (extra.range && extra.range !== 'all') {
            params.range = extra.range;

            if (extra.range === 'custom') {
                if (extra.from) {
params.from = extra.from;
}

                if (extra.to) {
params.to = extra.to;
}
            }
        }

        return params;
    };

    const apply = (next: Partial<Filters> & { page?: number }) => {
        clearSelection();
        const params: Record<string, string | number> = {};
        const merged = { ...filters, ...next };

        if (merged.search) {
            params.search = merged.search;
        }

        if (merged.status) {
            params.status = merged.status;
        }

        if (merged.payment_status) {
            params.payment_status = merged.payment_status;
        }

        if (merged.sort !== 'created_at' || merged.dir !== 'desc') {
            params.sort = merged.sort;
            params.dir = merged.dir;
        }

        if (merged.range && merged.range !== 'all') {
            params.range = merged.range;

            if (merged.range === 'custom') {
                if (merged.from) {
                    params.from = merged.from;
                }

                if (merged.to) {
                    params.to = merged.to;
                }
            }
        }

        if (merged.per_page && merged.per_page !== '20') {
            params.per_page = merged.per_page;
        }

        if (next.page && next.page > 1) {
            params.page = next.page;
        }

        router.get('/admin/orders', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const onSearch = (value: string) => {
        if (searchTimer.current) {
            clearTimeout(searchTimer.current);
        }

        searchTimer.current = setTimeout(() => apply({ search: value, page: 1 }), 400);
    };

    const onSort = (sortKey: string) => {
        const dir: SortDir =
            filters.sort === sortKey && filters.dir === 'desc' ? 'asc' : 'desc';
        apply({ sort: sortKey, dir, page: 1 });
    };

    const toggleRow = (key: string | number) => {
        setAllMatching(false);
        setSelected((prev) => {
            const nextSet = new Set(prev);

            if (nextSet.has(key)) {
                nextSet.delete(key);
            } else {
                nextSet.add(key);
            }

            return nextSet;
        });
    };

    const toggleAll = (keys: Array<string | number>) => {
        setAllMatching(false);
        setSelected((prev) => {
            const allOn = keys.length > 0 && keys.every((k) => prev.has(k));
            const nextSet = new Set(prev);
            keys.forEach((k) => (allOn ? nextSet.delete(k) : nextSet.add(k)));

            return nextSet;
        });
    };

    const pageSelectedCount = orders.filter((o) => selected.has(o.id)).length;
    const allPageSelected = orders.length > 0 && pageSelectedCount === orders.length;
    const effectiveCount = allMatching ? meta.total : selected.size;

    // Shared selection payload for both the status mutation and the downloads.
    const selectionParams = (): Record<string, string> =>
        allMatching
            ? { all_matching: '1', ...buildParams(filters) }
            : { ids: Array.from(selected).join(',') };

    const bulkStatus = (status: string) => {
        const base = allMatching
            ? { all_matching: true, filters: buildParams(filters) }
            : { ids: Array.from(selected) };
        router.post(
            '/admin/orders/bulk/status',
            { status, ...base },
            { preserveScroll: true, onSuccess: clearSelection },
        );
    };

    // Downloads are plain GET navigations (the response is a PDF attachment), so
    // the page stays put while the browser saves the file.
    const bulkDownload = (kind: 'invoices' | 'labels') => {
        const path = kind === 'labels' ? 'shipping-labels' : 'invoices';
        const qs = new URLSearchParams(selectionParams()).toString();
        window.location.href = `/admin/orders/bulk/${path}?${qs}`;
    };

    const columns: Column<OrderRow>[] = [
        {
            key: 'order_no',
            header: 'Order',
            sortKey: 'created_at',
            cell: (row) => (
                <div>
                    <div className="font-medium">{row.order_no}</div>
                    <div className="text-xs text-muted-foreground">{row.created_at}</div>
                </div>
            ),
        },
        {
            key: 'customer',
            header: 'Customer',
            cell: (row) => (
                <div>
                    <div>{row.customer ?? '—'}</div>
                    <div className="text-xs text-muted-foreground">{row.mobile}</div>
                </div>
            ),
        },
        { key: 'total', header: 'Total', sortKey: 'total', cell: (row) => <span className="font-medium whitespace-nowrap">{row.total}</span> },
        {
            key: 'status',
            header: 'Status',
            sortKey: 'status',
            cell: (row) => <RowStatus row={row} transitions={transitions} />,
        },
        {
            key: 'payment_status',
            header: 'Payment',
            cell: (row) => <span className="text-muted-foreground capitalize">{row.payment_status}</span>,
        },
        { key: 'actions', header: 'Actions', align: 'right', cell: (row) => <RowActions row={row} /> },
    ];

    const mobileCard = (row: OrderRow) => (
        <Link href={`/admin/orders/${row.id}`} className="flex items-center gap-3">
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{row.order_no}</div>
                <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <StatusBadge status={row.status} />
                    <span>{row.customer ?? '—'}</span>
                    <span>·</span>
                    <span>{row.total}</span>
                </div>
            </div>
            <Eye className="size-4 text-muted-foreground" />
        </Link>
    );

    const inputClass = 'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

    return (
        <>
            <Head title="Orders" />
            <div className="mx-auto w-full max-w-6xl p-4">
                <PageHeader
                    title="Orders"
                    description={`${meta.total} order${meta.total === 1 ? '' : 's'} placed.`}
                />

                <FilterBar
                    search={filters.search}
                    onSearch={onSearch}
                    placeholder="Search order no, name or mobile…"
                >
                    <select
                        aria-label="Filter by status"
                        defaultValue={filters.status}
                        onChange={(e) => apply({ status: e.target.value, page: 1 })}
                        className={inputClass}
                    >
                        <option value="">All statuses</option>
                        {statuses.map((s) => (
                            <option key={s} value={s} className="capitalize">
                                {s}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Filter by payment status"
                        defaultValue={filters.payment_status}
                        onChange={(e) => apply({ payment_status: e.target.value, page: 1 })}
                        className={inputClass}
                    >
                        <option value="">All payments</option>
                        {paymentStatuses.map((s) => (
                            <option key={s} value={s} className="capitalize">
                                {s}
                            </option>
                        ))}
                    </select>
                    <DateRangeFilter
                        value={{ range: filters.range, from: filters.from, to: filters.to }}
                        onChange={(v) => apply({ ...v, page: 1 })}
                    />
                </FilterBar>

                {orders.length === 0 ? (
                    <EmptyState
                        icon={ShoppingCart}
                        title="No orders found."
                        description="Orders placed from the storefront will appear here."
                    />
                ) : (
                    <>
                        {effectiveCount > 0 && (
                            <OrderBulkBar
                                count={effectiveCount}
                                statuses={statuses}
                                onStatus={bulkStatus}
                                onDownload={bulkDownload}
                                onClear={clearSelection}
                            />
                        )}

                        {(allPageSelected || allMatching) && meta.total > orders.length && (
                            <div className="mb-3 flex flex-wrap items-center justify-center gap-2 rounded-lg border border-accent/40 bg-accent/5 px-4 py-2 text-sm">
                                {allMatching ? (
                                    <>
                                        <span>
                                            All <strong>{meta.total}</strong> orders matching the
                                            current filters are selected.
                                        </span>
                                        <button
                                            type="button"
                                            onClick={clearSelection}
                                            className="font-medium text-accent underline-offset-2 hover:underline"
                                        >
                                            Clear selection
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <span>All {orders.length} on this page selected.</span>
                                        <button
                                            type="button"
                                            onClick={() => setAllMatching(true)}
                                            className="font-medium text-accent underline-offset-2 hover:underline"
                                        >
                                            Select all {meta.total} matching filters
                                        </button>
                                    </>
                                )}
                            </div>
                        )}

                        <DataTable
                            columns={columns}
                            rows={orders}
                            rowKey={(row) => row.id}
                            renderMobileCard={mobileCard}
                            sort={filters.sort}
                            dir={filters.dir}
                            onSort={onSort}
                            selection={{ selected, onToggle: toggleRow, onToggleAll: toggleAll }}
                        />

                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <span>Rows</span>
                                <select
                                    aria-label="Orders per page"
                                    value={filters.per_page}
                                    onChange={(e) => apply({ per_page: e.target.value, page: 1 })}
                                    className={inputClass}
                                >
                                    {PER_PAGE_OPTIONS.map((n) => (
                                        <option key={n} value={n}>
                                            {n} / page
                                        </option>
                                    ))}
                                </select>
                                {meta.last_page > 1 && (
                                    <span>
                                        · Page {meta.current_page} of {meta.last_page}
                                    </span>
                                )}
                            </div>

                            {meta.last_page > 1 && (
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page <= 1}
                                        onClick={() => apply({ page: meta.current_page - 1 })}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page >= meta.last_page}
                                        onClick={() => apply({ page: meta.current_page + 1 })}
                                    >
                                        Next
                                    </Button>
                                </div>
                            )}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [{ title: 'Orders', href: '/admin/orders' }],
};
