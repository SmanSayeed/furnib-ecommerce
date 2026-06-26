import { Head, router } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import { useRef } from 'react';
import { DataTable } from '@/components/admin/data-table';
import type { Column, SortDir } from '@/components/admin/data-table';
import { DateRangeFilter } from '@/components/admin/date-range-filter';
import type { DatePreset } from '@/components/admin/date-range-filter';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type InvoiceRow = {
    id: number;
    invoice_no: string;
    customer: string | null;
    mobile: string | null;
    total: string;
    payment_status: string;
    created_at: string | null;
};

type Filters = {
    search: string;
    payment_status: string;
    sort: string;
    dir: SortDir;
    range: DatePreset;
    from: string;
    to: string;
};

type Props = {
    invoices: InvoiceRow[];
    meta: { current_page: number; last_page: number; total: number };
    filters: Filters;
    paymentStatuses: string[];
};

const PAYMENT_STYLES: Record<string, string> = {
    paid: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    partial: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    unpaid: 'bg-muted text-muted-foreground',
};

function PaymentBadge({ status }: { status: string }) {
    return (
        <span
            className={`inline-block rounded-md px-2 py-0.5 text-xs font-medium capitalize ${PAYMENT_STYLES[status] ?? 'bg-muted text-muted-foreground'}`}
        >
            {status}
        </span>
    );
}

export default function InvoicesIndex({ invoices, meta, filters, paymentStatuses }: Props) {
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const apply = (next: Partial<Filters> & { page?: number }) => {
        const params: Record<string, string | number> = {};
        const merged = { ...filters, ...next };

        if (merged.search) {
            params.search = merged.search;
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

        router.get('/admin/invoices', params, {
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

    const inputClass = 'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

    const DownloadButton = ({ row }: { row: InvoiceRow }) => (
        <div className="flex justify-end">
            <Button variant="ghost" size="icon" asChild aria-label="Download invoice PDF">
                <a href={`/admin/orders/${row.id}/invoice`}>
                    <Download className="size-4" />
                </a>
            </Button>
        </div>
    );

    const columns: Column<InvoiceRow>[] = [
        {
            key: 'invoice_no',
            header: 'Invoice',
            sortKey: 'created_at',
            cell: (row) => (
                <div>
                    <div className="font-medium">{row.invoice_no}</div>
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
        { key: 'payment_status', header: 'Payment', cell: (row) => <PaymentBadge status={row.payment_status} /> },
        { key: 'actions', header: 'Actions', align: 'right', hideLabelOnMobile: true, cell: (row) => <DownloadButton row={row} /> },
    ];

    const mobileCard = (row: InvoiceRow) => (
        <div className="flex items-center gap-3">
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{row.invoice_no}</div>
                <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <PaymentBadge status={row.payment_status} />
                    <span>{row.customer ?? '—'}</span>
                    <span>·</span>
                    <span>{row.total}</span>
                </div>
            </div>
            <DownloadButton row={row} />
        </div>
    );

    return (
        <>
            <Head title="Invoices" />
            <div className="mx-auto w-full max-w-6xl p-4">
                <PageHeader
                    title="Invoices"
                    description={`${meta.total} invoice${meta.total === 1 ? '' : 's'}.`}
                />

                <FilterBar
                    search={filters.search}
                    onSearch={onSearch}
                    placeholder="Search invoice no, name or mobile…"
                >
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

                {invoices.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="No invoices found."
                        description="Invoices appear here as orders are placed."
                    />
                ) : (
                    <>
                        <DataTable
                            columns={columns}
                            rows={invoices}
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

InvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Invoices', href: '/admin/invoices' }],
};
