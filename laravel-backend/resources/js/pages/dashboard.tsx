import { Head, router } from '@inertiajs/react';
import { Banknote, Boxes, FolderTree, Package, ShoppingCart, TrendingUp, UserPlus } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {  DataTable } from '@/components/admin/data-table';
import type {Column} from '@/components/admin/data-table';
import { DateRangeFilter } from '@/components/admin/date-range-filter';
import type { DatePreset } from '@/components/admin/date-range-filter';
import { PageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import { dashboard } from '@/routes';

const BRAND = '#e85d1f';

type Stats = {
    products: number;
    published: number;
    categories: number;
    lowStock: number;
};

type OrderStats = {
    orders: number;
    revenue: string;
    advance_collected: string;
    new_customers: number;
    aov: string;
};

type SeriesPoint = { date: string; orders: number; revenue: number };

type Window = { range: DatePreset; from: string; to: string };

type RecentProduct = {
    id: number;
    title: string;
    sku: string;
    category: string | null;
    status: string;
    stock: number;
    price: string;
};

type Props = {
    stats: Stats;
    orderStats: OrderStats;
    series: SeriesPoint[];
    window: Window;
    byCategory: { name: string; products: number }[];
    recentProducts: RecentProduct[];
};

function StatusPill({ status }: { status: string }) {
    const published = status === 'published';

    return (
        <span
            className={`inline-block rounded-md px-2 py-0.5 text-xs font-medium ${
                published
                    ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                    : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
            }`}
        >
            {published ? 'Published' : 'Draft'}
        </span>
    );
}

export default function Dashboard({
    stats,
    orderStats,
    series,
    window,
    byCategory,
    recentProducts,
}: Props) {
    const applyWindow = (v: Window) => {
        const params: Record<string, string> = {};

        if (v.range && v.range !== 'this_month') {
            params.range = v.range;

            if (v.range === 'custom') {
                if (v.from) {
                    params.from = v.from;
                }

                if (v.to) {
                    params.to = v.to;
                }
            }
        }

        router.get('/dashboard', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const recentColumns: Column<RecentProduct>[] = [
        {
            key: 'title',
            header: 'Product',
            cell: (p) => (
                <div className="min-w-0">
                    <div className="truncate font-medium">{p.title}</div>
                    <div className="text-xs text-muted-foreground">{p.sku}</div>
                </div>
            ),
        },
        {
            key: 'category',
            header: 'Category',
            cell: (p) => <span className="text-muted-foreground">{p.category ?? '—'}</span>,
        },
        { key: 'status', header: 'Status', cell: (p) => <StatusPill status={p.status} /> },
        { key: 'stock', header: 'Stock', cell: (p) => p.stock },
        {
            key: 'price',
            header: 'Price',
            align: 'right',
            cell: (p) => <span className="font-medium">{p.price}</span>,
        },
    ];

    const recentMobile = (p: RecentProduct) => (
        <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
                <div className="truncate font-medium">{p.title}</div>
                <div className="text-xs text-muted-foreground">{p.sku}</div>
                <div className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                    <span>{p.category ?? '—'}</span>
                    <span>·</span>
                    <span>Stock {p.stock}</span>
                </div>
            </div>
            <div className="flex flex-col items-end gap-1">
                <span className="font-medium">{p.price}</span>
                <StatusPill status={p.status} />
            </div>
        </div>
    );

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4 p-4">
                <PageHeader
                    title="Dashboard"
                    description="Your store at a glance."
                    actions={
                        <DateRangeFilter
                            value={{ range: window.range, from: window.from, to: window.to }}
                            onChange={applyWindow}
                        />
                    }
                />

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatCard label="Orders" value={orderStats.orders.toLocaleString()} icon={ShoppingCart} />
                    <StatCard label="Revenue (paid)" value={orderStats.revenue} icon={Banknote} />
                    <StatCard label="Avg order value" value={orderStats.aov} icon={TrendingUp} />
                    <StatCard label="New customers" value={orderStats.new_customers.toLocaleString()} icon={UserPlus} />
                </div>

                <div className="rounded-xl border bg-card p-4">
                    <h2 className="text-sm font-medium text-muted-foreground">Orders &amp; revenue</h2>
                    {series.length === 0 ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">
                            No orders in this window.
                        </p>
                    ) : (
                        <div className="mt-4 h-[280px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <ComposedChart data={series} margin={{ top: 8, right: 8, left: -16, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="currentColor" className="text-border" vertical={false} />
                                    <XAxis dataKey="date" tick={{ fontSize: 11 }} stroke="currentColor" className="text-muted-foreground" />
                                    <YAxis yAxisId="left" allowDecimals={false} tick={{ fontSize: 11 }} stroke="currentColor" className="text-muted-foreground" />
                                    <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 11 }} stroke="currentColor" className="text-muted-foreground" />
                                    <Tooltip cursor={{ fill: 'rgba(232,93,31,0.08)' }} />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Bar yAxisId="left" dataKey="orders" name="Orders" fill={BRAND} radius={[4, 4, 0, 0]} maxBarSize={40} />
                                    <Line yAxisId="right" type="monotone" dataKey="revenue" name="Revenue (৳)" stroke="#2563eb" strokeWidth={2} dot={false} />
                                </ComposedChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatCard label="Products" value={stats.products.toLocaleString()} icon={Package} />
                    <StatCard
                        label="Published"
                        value={stats.published.toLocaleString()}
                        icon={ShoppingCart}
                    />
                    <StatCard
                        label="Categories"
                        value={stats.categories.toLocaleString()}
                        icon={FolderTree}
                    />
                    <StatCard
                        label="Low stock (≤5)"
                        value={stats.lowStock.toLocaleString()}
                        icon={Boxes}
                    />
                </div>

                <div className="rounded-xl border bg-card p-4">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Products by category
                    </h2>
                    {byCategory.length === 0 ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">
                            No categories yet.
                        </p>
                    ) : (
                        <div className="mt-4 h-[260px] w-full">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={byCategory} margin={{ top: 8, right: 8, left: -16, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="currentColor" className="text-border" vertical={false} />
                                    <XAxis dataKey="name" tick={{ fontSize: 12 }} stroke="currentColor" className="text-muted-foreground" />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 12 }} stroke="currentColor" className="text-muted-foreground" />
                                    <Tooltip cursor={{ fill: 'rgba(232,93,31,0.08)' }} />
                                    <Bar dataKey="products" fill={BRAND} radius={[4, 4, 0, 0]} maxBarSize={64} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </div>

                <div>
                    <h2 className="mb-2 text-sm font-medium text-muted-foreground">Recent products</h2>
                    {recentProducts.length === 0 ? (
                        <div className="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground">
                            No products yet.
                        </div>
                    ) : (
                        <DataTable
                            columns={recentColumns}
                            rows={recentProducts}
                            rowKey={(p) => p.id}
                            renderMobileCard={recentMobile}
                        />
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
