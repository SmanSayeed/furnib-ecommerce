import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';
import { useState } from 'react';
import {  DataTable } from '@/components/admin/data-table';
import type {Column} from '@/components/admin/data-table';
import { PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

export const PENDING_REASON_LABELS: Record<string, string> = {
    new_order: 'New order',
    call_waiting: 'Call waiting',
    payment_pending: 'Payment pending',
    need_expert_call: 'Need expert call',
    other: 'Other',
};

type Item = {
    title: string;
    sku: string | null;
    price: string;
    qty: number;
    line_total: string;
};

type Order = {
    id: number;
    order_no: string;
    status: string;
    pending_reason: string;
    pending_note: string | null;
    payment_status: string;
    subtotal: string;
    shipping_cost: string;
    total: string;
    address: string;
    notes: string | null;
    created_at: string | null;
    customer: { name: string | null; mobile: string | null; email: string | null };
    shipping_zone: string | null;
    items: Item[];
};

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex justify-between gap-4 py-1 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value ?? '—'}</span>
        </div>
    );
}

export default function OrderShow({
    order,
    nextStatuses,
    pendingReasons,
}: {
    order: Order;
    nextStatuses: string[];
    pendingReasons: string[];
}) {
    const [reason, setReason] = useState(order.pending_reason);
    const itemColumns: Column<Item>[] = [
        {
            key: 'title',
            header: 'Item',
            cell: (i) => (
                <div>
                    <div className="font-medium">{i.title}</div>
                    <div className="text-xs text-muted-foreground">{i.sku}</div>
                </div>
            ),
        },
        { key: 'price', header: 'Price', cell: (i) => i.price },
        { key: 'qty', header: 'Qty', cell: (i) => i.qty },
        { key: 'line_total', header: 'Line total', align: 'right', cell: (i) => <span className="font-medium">{i.line_total}</span> },
    ];

    return (
        <>
            <Head title={`Order ${order.order_no}`} />
            <div className="mx-auto w-full max-w-4xl p-4">
                <PageHeader
                    title={order.order_no}
                    description={`Placed ${order.created_at ?? ''} · ${order.status}`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a href={`/admin/orders/${order.id}/invoice`}>
                                    <FileText className="size-4" /> Invoice PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/admin/orders">
                                    <ArrowLeft className="size-4" /> Back
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <div className="rounded-xl border bg-card p-4">
                            <h2 className="mb-2 text-sm font-medium text-muted-foreground">Items</h2>
                            <DataTable
                                columns={itemColumns}
                                rows={order.items}
                                rowKey={(i) => `${i.sku ?? i.title}-${i.qty}`}
                            />
                            <div className="mt-3 space-y-1 border-t pt-3">
                                <Row label="Subtotal" value={order.subtotal} />
                                <Row label="Shipping" value={order.shipping_cost} />
                                <Row label="Total" value={order.total} />
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        {order.status === 'pending' && (
                            <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
                                <h2 className="mb-2 text-sm font-medium text-amber-700 dark:text-amber-400">
                                    Pending reason
                                </h2>
                                <p className="mb-3 text-xs text-muted-foreground">
                                    Why this order is still open. It stays pending until you confirm it — even if paid.
                                </p>
                                <Form
                                    method="put"
                                    action={`/admin/orders/${order.id}/pending`}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing, errors }) => (
                                        <div className="space-y-3">
                                            <select
                                                name="pending_reason"
                                                value={reason}
                                                onChange={(e) => setReason(e.target.value)}
                                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                            >
                                                {pendingReasons.map((r) => (
                                                    <option key={r} value={r}>
                                                        {PENDING_REASON_LABELS[r] ?? r}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.pending_reason} />
                                            {reason === 'other' && (
                                                <>
                                                    <textarea
                                                        name="pending_note"
                                                        defaultValue={order.pending_note ?? ''}
                                                        rows={2}
                                                        maxLength={500}
                                                        placeholder="Write the reason…"
                                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                                    />
                                                    <InputError message={errors.pending_note} />
                                                </>
                                            )}
                                            <Button type="submit" variant="outline" className="w-full" disabled={processing}>
                                                Save reason
                                            </Button>
                                        </div>
                                    )}
                                </Form>
                            </div>
                        )}

                        <div className="rounded-xl border bg-card p-4">
                            <h2 className="mb-2 text-sm font-medium text-muted-foreground">Update status</h2>
                            <Form method="put" action={`/admin/orders/${order.id}/status`} options={{ preserveScroll: true }}>
                                {({ processing, errors }) => (
                                    <div className="space-y-3">
                                        <select
                                            name="status"
                                            defaultValue=""
                                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                            disabled={nextStatuses.length === 0}
                                        >
                                            <option value="" disabled>
                                                {nextStatuses.length ? 'Choose next status…' : 'No further transitions'}
                                            </option>
                                            {nextStatuses.map((s) => (
                                                <option key={s} value={s} className="capitalize">
                                                    {s}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.status} />
                                        <Button
                                            type="submit"
                                            className="w-full"
                                            disabled={processing || nextStatuses.length === 0}
                                        >
                                            Update status
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        </div>

                        <div className="rounded-xl border bg-card p-4">
                            <h2 className="mb-2 text-sm font-medium text-muted-foreground">Customer</h2>
                            <Row label="Name" value={order.customer.name} />
                            <Row label="Mobile" value={order.customer.mobile} />
                            <Row label="Email" value={order.customer.email} />
                            <Row label="Payment" value={order.payment_status} />
                            <Row label="Zone" value={order.shipping_zone} />
                        </div>

                        <div className="rounded-xl border bg-card p-4">
                            <h2 className="mb-2 text-sm font-medium text-muted-foreground">Delivery address</h2>
                            <p className="text-sm whitespace-pre-wrap">{order.address}</p>
                            {order.notes && (
                                <p className="mt-2 text-sm text-muted-foreground">Notes: {order.notes}</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

OrderShow.layout = {
    breadcrumbs: [
        { title: 'Orders', href: '/admin/orders' },
        { title: 'Detail', href: '#' },
    ],
};
