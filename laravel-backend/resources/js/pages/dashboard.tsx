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

function Kpi({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number;
    icon: React.ComponentType<{ className?: string }>;
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">{label}</span>
                <Icon className="size-4 text-muted-foreground" />
            </div>
            <div className="mt-2 text-2xl font-semibold">{value.toLocaleString()}</div>
        </div>
    );
}

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
    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4 p-4">
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Kpi label="Products" value={stats.products} icon={Package} />
                    <Kpi label="Published" value={stats.published} icon={ShoppingCart} />
                    <Kpi label="Categories" value={stats.categories} icon={FolderTree} />
                    <Kpi label="Low stock (≤5)" value={stats.lowStock} icon={Boxes} />
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

                <div className="rounded-xl border bg-card">
                    <div className="border-b p-4">
                        <h2 className="text-sm font-medium text-muted-foreground">Recent products</h2>
                    </div>

                    {recentProducts.length === 0 ? (
                        <p className="p-10 text-center text-sm text-muted-foreground">
                            No products yet.
                        </p>
                    ) : (
                        <>
                            <table className="hidden w-full text-sm md:table">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="px-4 py-2 font-normal">Product</th>
                                        <th className="px-4 py-2 font-normal">Category</th>
                                        <th className="px-4 py-2 font-normal">Status</th>
                                        <th className="px-4 py-2 font-normal">Stock</th>
                                        <th className="px-4 py-2 text-right font-normal">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentProducts.map((p) => (
                                        <tr key={p.id} className="border-b last:border-0">
                                            <td className="px-4 py-3">
                                                <div className="font-medium">{p.title}</div>
                                                <div className="text-xs text-muted-foreground">{p.sku}</div>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">{p.category ?? '—'}</td>
                                            <td className="px-4 py-3"><StatusPill status={p.status} /></td>
                                            <td className="px-4 py-3">{p.stock}</td>
                                            <td className="px-4 py-3 text-right font-medium">{p.price}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            <div className="divide-y md:hidden">
                                {recentProducts.map((p) => (
                                    <div key={p.id} className="flex items-start justify-between gap-3 p-4">
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
                                ))}
                            </div>
                        </>
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
