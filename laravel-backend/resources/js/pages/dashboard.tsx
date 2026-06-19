import { Head } from '@inertiajs/react';
import { Boxes, FolderTree, Package, ShoppingCart } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {  DataTable } from '@/components/admin/data-table';
import type {Column} from '@/components/admin/data-table';
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

export default function Dashboard({ stats, byCategory, recentProducts }: Props) {
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
                <PageHeader title="Dashboard" description="Your store at a glance." />

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

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-xl border bg-card p-4 lg:col-span-2">
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

                    <div className="flex flex-col rounded-xl border bg-card p-4">
                        <h2 className="text-sm font-medium text-muted-foreground">Orders & revenue</h2>
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 py-8 text-center">
                            <ShoppingCart className="size-8 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">
                                Live order & revenue analytics arrive with the Orders module (Phase 3).
                            </p>
                        </div>
                    </div>
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
