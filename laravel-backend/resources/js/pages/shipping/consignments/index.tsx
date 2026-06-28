import { Head } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import { DataTable } from '@/components/admin/data-table';
import type { Column } from '@/components/admin/data-table';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';

type Shipment = {
    id: number;
    order_no: string | null;
    courier: string;
    consignment_id: string | null;
    tracking_code: string | null;
    status: string;
    cod_amount: string;
    at: string | null;
};

export default function ConsignmentsIndex({ shipments }: { shipments: Shipment[] }) {
    const columns: Column<Shipment>[] = [
        {
            key: 'order_no',
            header: 'Order',
            cell: (s) => <span className="font-medium">{s.order_no ?? '—'}</span>,
        },
        { key: 'courier', header: 'Courier', cell: (s) => <span className="capitalize">{s.courier}</span> },
        {
            key: 'consignment_id',
            header: 'Consignment',
            cell: (s) => <span className="font-mono text-xs text-muted-foreground">{s.consignment_id ?? '—'}</span>,
        },
        {
            key: 'tracking_code',
            header: 'Tracking',
            cell: (s) => <span className="font-mono text-xs text-muted-foreground">{s.tracking_code ?? '—'}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (s) => (
                <span className="inline-block rounded-md bg-muted px-2 py-0.5 text-xs font-medium capitalize">
                    {s.status}
                </span>
            ),
        },
        { key: 'cod_amount', header: 'COD', cell: (s) => <span className="font-medium">{s.cod_amount}</span> },
        { key: 'at', header: 'Date', cell: (s) => <span className="text-muted-foreground">{s.at}</span> },
    ];

    const mobileCard = (s: Shipment) => (
        <div className="space-y-1">
            <div className="flex items-center justify-between">
                <span className="font-medium">{s.order_no ?? '—'}</span>
                <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium capitalize">
                    {s.status}
                </span>
            </div>
            <div className="text-xs text-muted-foreground capitalize">
                {s.courier} · COD {s.cod_amount}
            </div>
            <div className="font-mono text-[11px] text-muted-foreground">
                {s.consignment_id ?? '—'} / {s.tracking_code ?? '—'}
            </div>
            <div className="text-xs text-muted-foreground">{s.at}</div>
        </div>
    );

    return (
        <>
            <Head title="Consignments" />
            <div className="mx-auto w-full max-w-6xl p-4">
                <PageHeader
                    title="Consignments"
                    description="Courier bookings for orders (read-only)."
                />

                {shipments.length === 0 ? (
                    <EmptyState icon={Truck} title="No consignments yet." />
                ) : (
                    <DataTable
                        columns={columns}
                        rows={shipments}
                        rowKey={(s) => s.id}
                        renderMobileCard={mobileCard}
                    />
                )}
            </div>
        </>
    );
}

ConsignmentsIndex.layout = {
    breadcrumbs: [{ title: 'Consignments', href: '/admin/shipping/consignments' }],
};
