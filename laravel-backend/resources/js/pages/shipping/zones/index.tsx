import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Truck } from 'lucide-react';
import {  DataTable } from '@/components/admin/data-table';
import type {Column} from '@/components/admin/data-table';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type Zone = {
    id: number;
    name: string;
    cost: string;
    status: boolean;
    position_order: number;
};

function StatusBadge({ active }: { active: boolean }) {
    return (
        <span
            className={`inline-block rounded-md px-2 py-0.5 text-xs font-medium ${
                active
                    ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                    : 'bg-muted text-muted-foreground'
            }`}
        >
            {active ? 'Active' : 'Hidden'}
        </span>
    );
}

export default function ZonesIndex({ zones }: { zones: Zone[] }) {
    const remove = (z: Zone) =>
        router.delete(`/admin/shipping/zones/${z.id}`, {
            preserveScroll: true,
            onBefore: () => confirm(`Delete shipping zone “${z.name}”?`),
        });

    const RowActions = ({ z }: { z: Zone }) => (
        <div className="flex justify-end gap-1">
            <Button variant="ghost" size="icon" asChild aria-label="Edit">
                <Link href={`/admin/shipping/zones/${z.id}/edit`}>
                    <Pencil className="size-4" />
                </Link>
            </Button>
            <Button variant="ghost" size="icon" aria-label="Delete" onClick={() => remove(z)}>
                <Trash2 className="size-4 text-destructive" />
            </Button>
        </div>
    );

    const columns: Column<Zone>[] = [
        {
            key: 'name',
            header: 'Zone',
            cell: (z) => <span className="font-medium">{z.name}</span>,
        },
        {
            key: 'cost',
            header: 'Shipping cost',
            cell: (z) => <span className="whitespace-nowrap">{z.cost}</span>,
        },
        {
            key: 'position_order',
            header: 'Order',
            cell: (z) => <span className="text-muted-foreground">{z.position_order}</span>,
        },
        { key: 'status', header: 'Status', cell: (z) => <StatusBadge active={z.status} /> },
        { key: 'actions', header: 'Actions', align: 'right', cell: (z) => <RowActions z={z} /> },
    ];

    const mobileCard = (z: Zone) => (
        <div className="flex items-center gap-3">
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{z.name}</div>
                <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                    <StatusBadge active={z.status} />
                    <span>{z.cost}</span>
                </div>
            </div>
            <RowActions z={z} />
        </div>
    );

    return (
        <>
            <Head title="Shipping zones" />
            <div className="mx-auto w-full max-w-4xl p-4">
                <PageHeader
                    title="Shipping zones"
                    description="Delivery areas and their shipping cost, used at checkout."
                    actions={
                        <Button asChild>
                            <Link href="/admin/shipping/zones/create">
                                <Plus className="size-4" /> New zone
                            </Link>
                        </Button>
                    }
                />

                {zones.length === 0 ? (
                    <EmptyState
                        icon={Truck}
                        title="No shipping zones yet."
                        description="Add delivery areas so customers can pick one at checkout."
                        action={
                            <Button asChild>
                                <Link href="/admin/shipping/zones/create">
                                    <Plus className="size-4" /> Create your first zone
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        rows={zones}
                        rowKey={(z) => z.id}
                        renderMobileCard={mobileCard}
                    />
                )}
            </div>
        </>
    );
}

ZonesIndex.layout = {
    breadcrumbs: [{ title: 'Shipping zones', href: '/admin/shipping/zones' }],
};
