import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Truck } from 'lucide-react';
import { DataTable } from '@/components/admin/data-table';
import type { Column } from '@/components/admin/data-table';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type Courier = {
    id: number;
    name: string;
    slug: string;
    driver: string;
    is_api: boolean;
    is_active: boolean;
    is_default: boolean;
    configured: boolean;
    position_order: number;
};

function Badge({ tone, children }: { tone: 'green' | 'amber' | 'muted' | 'blue'; children: React.ReactNode }) {
    const tones: Record<string, string> = {
        green: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        blue: 'bg-blue-500/15 text-blue-600 dark:text-blue-400',
        muted: 'bg-muted text-muted-foreground',
    };

    return (
        <span className={`inline-block rounded-md px-2 py-0.5 text-xs font-medium ${tones[tone]}`}>
            {children}
        </span>
    );
}

function StatusCell({ c }: { c: Courier }) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <Badge tone={c.is_active ? 'green' : 'muted'}>{c.is_active ? 'Active' : 'Off'}</Badge>
            {c.is_default && <Badge tone="blue">Default</Badge>}
            {c.is_api && !c.configured && <Badge tone="amber">Needs credentials</Badge>}
        </div>
    );
}

export default function CouriersIndex({ couriers }: { couriers: Courier[] }) {
    const remove = (c: Courier) =>
        router.delete(`/admin/shipping/couriers/${c.id}`, {
            preserveScroll: true,
            onBefore: () => confirm(`Remove courier “${c.name}”? Existing shipments keep its name.`),
        });

    const RowActions = ({ c }: { c: Courier }) => (
        <div className="flex justify-end gap-1">
            <Button variant="ghost" size="icon" asChild aria-label="Edit">
                <Link href={`/admin/shipping/couriers/${c.id}/edit`}>
                    <Pencil className="size-4" />
                </Link>
            </Button>
            <Button variant="ghost" size="icon" aria-label="Delete" onClick={() => remove(c)}>
                <Trash2 className="size-4 text-destructive" />
            </Button>
        </div>
    );

    const columns: Column<Courier>[] = [
        {
            key: 'name',
            header: 'Courier',
            cell: (c) => (
                <div>
                    <span className="font-medium">{c.name}</span>
                    <span className="ml-2 text-xs text-muted-foreground">{c.slug}</span>
                </div>
            ),
        },
        {
            key: 'driver',
            header: 'Type',
            cell: (c) => (
                <span className="whitespace-nowrap capitalize">
                    {c.is_api ? c.driver : 'Manual (no API)'}
                </span>
            ),
        },
        { key: 'status', header: 'Status', cell: (c) => <StatusCell c={c} /> },
        {
            key: 'position_order',
            header: 'Order',
            cell: (c) => <span className="text-muted-foreground">{c.position_order}</span>,
        },
        { key: 'actions', header: 'Actions', align: 'right', cell: (c) => <RowActions c={c} /> },
    ];

    const mobileCard = (c: Courier) => (
        <div className="flex items-center gap-3">
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{c.name}</div>
                <div className="mt-1">
                    <StatusCell c={c} />
                </div>
            </div>
            <RowActions c={c} />
        </div>
    );

    return (
        <>
            <Head title="Couriers" />
            <div className="mx-auto w-full max-w-4xl p-4">
                <PageHeader
                    title="Couriers"
                    description="Manage delivery partners. API couriers (Steadfast) book automatically; a manual courier is booked by hand and its name still prints on the label. The default is auto-booked when an order is confirmed."
                    actions={
                        <Button asChild>
                            <Link href="/admin/shipping/couriers/create">
                                <Plus className="size-4" /> New courier
                            </Link>
                        </Button>
                    }
                />

                {couriers.length === 0 ? (
                    <EmptyState
                        icon={Truck}
                        title="No couriers yet."
                        description="Add a courier so orders can be shipped."
                        action={
                            <Button asChild>
                                <Link href="/admin/shipping/couriers/create">
                                    <Plus className="size-4" /> Create your first courier
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        rows={couriers}
                        rowKey={(c) => c.id}
                        renderMobileCard={mobileCard}
                    />
                )}
            </div>
        </>
    );
}

CouriersIndex.layout = {
    breadcrumbs: [{ title: 'Couriers', href: '/admin/shipping/couriers' }],
};
