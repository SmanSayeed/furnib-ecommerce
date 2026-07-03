import { Head, Link, router } from '@inertiajs/react';
import { Eye, ShoppingCart } from 'lucide-react';
import { useRef } from 'react';
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
};

type Props = {
    orders: OrderRow[];
    meta: { current_page: number; last_page: number; total: number };
    filters: Filters;
    statuses: string[];
    paymentStatuses: string[];
};

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

export default function OrdersIndex({ orders, meta, filters, statuses, paymentStatuses }: Props) {
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const apply = (next: Partial<Filters> & { page?: number }) => {
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

    const ViewButton = ({ row }: { row: OrderRow }) => (
        <div className="flex justify-end">
            <Button variant="ghost" size="icon" asChild aria-label="View order">
                <Link href={`/admin/orders/${row.id}`}>
                    <Eye className="size-4" />
                </Link>
            </Button>
        </div>
    );

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
            cell: (row) => (
                <div className="flex flex-col items-start gap-1">
                    <StatusBadge status={row.status} />
                    {row.pending_reason && (
                        <span className="text-xs text-muted-foreground">
                            {PENDING_REASON_LABELS[row.pending_reason] ?? row.pending_reason}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'payment_status',
            header: 'Payment',
            cell: (row) => <span className="text-muted-foreground capitalize">{row.payment_status}</span>,
        },
        { key: 'actions', header: 'Actions', align: 'right', cell: (row) => <ViewButton row={row} /> },
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
                        <DataTable
                            columns={columns}
                            rows={orders}
                            rowKey={(row) => row.id}
                            renderMobileCard={mobileCard}
                            sort={filters.sort}
                            dir={filters.dir}
                            onSort={onSort}
                        />

                        {meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Page {meta.current_page} of {meta.last_page}
                                </span>
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
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [{ title: 'Orders', href: '/admin/orders' }],
};
