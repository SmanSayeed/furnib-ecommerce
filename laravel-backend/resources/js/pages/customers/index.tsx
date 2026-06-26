import { Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useRef } from 'react';
import { DataTable } from '@/components/admin/data-table';
import type { Column, SortDir } from '@/components/admin/data-table';
import { DateRangeFilter } from '@/components/admin/date-range-filter';
import type { DatePreset } from '@/components/admin/date-range-filter';
import { EmptyState } from '@/components/admin/empty-state';
import { FilterBar } from '@/components/admin/filter-bar';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type CustomerRow = {
    id: number;
    name: string | null;
    mobile: string;
    email: string | null;
    orders_count: number;
    total_spent: string;
    joined: string | null;
};

type Filters = {
    search: string;
    sort: string;
    dir: SortDir;
    range: DatePreset;
    from: string;
    to: string;
};

type Props = {
    customers: CustomerRow[];
    meta: { current_page: number; last_page: number; total: number };
    filters: Filters;
};

export default function CustomersIndex({ customers, meta, filters }: Props) {
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const apply = (next: Partial<Filters> & { page?: number }) => {
        const params: Record<string, string | number> = {};
        const merged = { ...filters, ...next };

        if (merged.search) {
            params.search = merged.search;
        }

        if (merged.sort !== 'joined' || merged.dir !== 'desc') {
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

        router.get('/admin/customers', params, {
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

    const columns: Column<CustomerRow>[] = [
        {
            key: 'name',
            header: 'Customer',
            sortKey: 'name',
            cell: (row) => (
                <div>
                    <div className="font-medium">{row.name ?? '—'}</div>
                    <div className="text-xs text-muted-foreground">{row.email ?? ''}</div>
                </div>
            ),
        },
        { key: 'mobile', header: 'Mobile', cell: (row) => <span className="whitespace-nowrap">{row.mobile}</span> },
        {
            key: 'orders_count',
            header: 'Orders',
            sortKey: 'orders_count',
            align: 'right',
            cell: (row) => <span>{row.orders_count}</span>,
        },
        {
            key: 'total_spent',
            header: 'Total spent',
            sortKey: 'total_spent',
            align: 'right',
            cell: (row) => <span className="font-medium whitespace-nowrap">{row.total_spent}</span>,
        },
        { key: 'joined', header: 'Joined', sortKey: 'joined', cell: (row) => <span className="text-muted-foreground">{row.joined ?? '—'}</span> },
    ];

    const mobileCard = (row: CustomerRow) => (
        <div>
            <div className="font-medium">{row.name ?? '—'}</div>
            <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span>{row.mobile}</span>
                <span>·</span>
                <span>
                    {row.orders_count} order{row.orders_count === 1 ? '' : 's'}
                </span>
                <span>·</span>
                <span className="font-medium text-foreground">{row.total_spent}</span>
            </div>
        </div>
    );

    return (
        <>
            <Head title="Customers" />
            <div className="mx-auto w-full max-w-6xl p-4">
                <PageHeader
                    title="Customers"
                    description={`${meta.total} customer${meta.total === 1 ? '' : 's'}.`}
                />

                <FilterBar
                    search={filters.search}
                    onSearch={onSearch}
                    placeholder="Search by name, mobile or email…"
                >
                    <DateRangeFilter
                        value={{ range: filters.range, from: filters.from, to: filters.to }}
                        onChange={(v) => apply({ ...v, page: 1 })}
                    />
                </FilterBar>

                {customers.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No customers found."
                        description="Customers are created automatically when an order is placed."
                    />
                ) : (
                    <>
                        <DataTable
                            columns={columns}
                            rows={customers}
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

CustomersIndex.layout = {
    breadcrumbs: [{ title: 'Customers', href: '/admin/customers' }],
};
