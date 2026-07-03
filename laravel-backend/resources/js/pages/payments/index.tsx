import { Head } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import { DataTable } from '@/components/admin/data-table';
import type { Column } from '@/components/admin/data-table';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';

type Payment = {
    id: number;
    order_no: string | null;
    gateway: string;
    amount: string;
    type: string;
    status: string;
    note: string | null;
    tran_id: string;
    val_id: string | null;
    at: string | null;
};

function StatusBadge({ status }: { status: string }) {
    const tone =
        status === 'success'
            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
            : status === 'failed'
              ? 'bg-red-500/15 text-red-600 dark:text-red-400'
              : status === 'cancelled'
                ? 'bg-slate-500/15 text-slate-600 dark:text-slate-300'
                : 'bg-amber-500/15 text-amber-600 dark:text-amber-400';

    return (
        <span className={`inline-block rounded-md px-2 py-0.5 text-xs font-medium ${tone}`}>
            {status}
        </span>
    );
}

export default function PaymentsIndex({ payments }: { payments: Payment[] }) {
    const columns: Column<Payment>[] = [
        {
            key: 'order_no',
            header: 'Order',
            cell: (p) => <span className="font-medium">{p.order_no ?? '—'}</span>,
        },
        { key: 'gateway', header: 'Gateway', cell: (p) => <span className="capitalize">{p.gateway}</span> },
        { key: 'amount', header: 'Amount', cell: (p) => <span className="font-medium">{p.amount}</span> },
        { key: 'type', header: 'Type', cell: (p) => <span className="capitalize text-muted-foreground">{p.type}</span> },
        {
            key: 'status',
            header: 'Status',
            cell: (p) => (
                <div className="space-y-1">
                    <StatusBadge status={p.status} />
                    {p.note && <p className="max-w-[16rem] text-[11px] text-muted-foreground">{p.note}</p>}
                </div>
            ),
        },
        {
            key: 'tran_id',
            header: 'Transaction ID',
            cell: (p) => <span className="font-mono text-xs text-muted-foreground">{p.tran_id}</span>,
        },
        { key: 'at', header: 'Date', cell: (p) => <span className="text-muted-foreground">{p.at}</span> },
    ];

    const mobileCard = (p: Payment) => (
        <div className="space-y-1">
            <div className="flex items-center justify-between">
                <span className="font-medium">{p.order_no ?? '—'}</span>
                <StatusBadge status={p.status} />
            </div>
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span className="capitalize">{p.gateway} · {p.type}</span>
                <span className="font-medium text-foreground">{p.amount}</span>
            </div>
            {p.note && <div className="text-[11px] text-muted-foreground">{p.note}</div>}
            <div className="font-mono text-[11px] text-muted-foreground">{p.tran_id}</div>
            <div className="text-xs text-muted-foreground">{p.at}</div>
        </div>
    );

    return (
        <>
            <Head title="Transactions" />
            <div className="mx-auto w-full max-w-6xl p-4">
                <PageHeader
                    title="Transactions"
                    description="Gateway payment attempts against orders (read-only)."
                />

                {payments.length === 0 ? (
                    <EmptyState icon={CreditCard} title="No transactions yet." />
                ) : (
                    <DataTable
                        columns={columns}
                        rows={payments}
                        rowKey={(p) => p.id}
                        renderMobileCard={mobileCard}
                    />
                )}
            </div>
        </>
    );
}

PaymentsIndex.layout = {
    breadcrumbs: [{ title: 'Transactions', href: '/admin/payments' }],
};
