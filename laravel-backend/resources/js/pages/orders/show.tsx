import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, Ticket } from 'lucide-react';
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

type PaymentRow = {
    id: number;
    gateway: string;
    amount: string;
    type: string;
    direction: string;
    status: string;
    note: string | null;
    at: string | null;
};

type ShipmentInfo = {
    courier: string;
    consignment_id: string | null;
    tracking_code: string | null;
    status: string;
    cod_amount: string;
};

type CourierStats = {
    phone: string;
    total: number;
    delivered: number;
    cancelled: number;
    returned: number;
    completed: number;
    in_flight: number;
    fraud_score: number;
    success_rate: number;
    risk: 'new' | 'low' | 'medium' | 'high';
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
    advance_paid: string;
    due: string;
    address: string;
    notes: string | null;
    created_at: string | null;
    customer: { name: string | null; mobile: string | null; email: string | null };
    shipping_zone: string | null;
    items: Item[];
    payments: PaymentRow[];
    shipment: ShipmentInfo | null;
};

const RISK_STYLES: Record<string, string> = {
    high: 'bg-red-500/15 text-red-600 dark:text-red-400',
    medium: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    low: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    new: 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
};

const RISK_LABELS: Record<string, string> = {
    high: 'High risk',
    medium: 'Some risk',
    low: 'Good history',
    new: 'New customer',
};

function CourierRiskCard({ stats }: { stats: CourierStats }) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <div className="mb-2 flex items-center justify-between gap-2">
                <h2 className="text-sm font-medium text-muted-foreground">Courier history</h2>
                <span className={`rounded px-1.5 py-0.5 text-[11px] font-medium ${RISK_STYLES[stats.risk]}`}>
                    {RISK_LABELS[stats.risk]}
                </span>
            </div>
            {stats.total === 0 ? (
                <p className="text-sm text-muted-foreground">No past deliveries for this number.</p>
            ) : (
                <>
                    <Row label="Delivered" value={String(stats.delivered)} />
                    <Row label="Cancelled" value={String(stats.cancelled)} />
                    <Row label="Returned" value={String(stats.returned)} />
                    <Row label="In transit" value={String(stats.in_flight)} />
                    <div className="mt-1 flex justify-between gap-4 border-t pt-2 text-sm font-semibold">
                        <span>Fail ratio</span>
                        <span className="text-right">{Math.round(stats.fraud_score * 100)}%</span>
                    </div>
                    {stats.risk === 'high' && (
                        <p className="mt-2 text-xs text-red-600 dark:text-red-400">
                            ⚠️ Many cancels/returns — consider requiring an advance before shipping COD.
                        </p>
                    )}
                </>
            )}
        </div>
    );
}

function ShipmentCard({ shipment }: { shipment: ShipmentInfo }) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <h2 className="mb-2 text-sm font-medium text-muted-foreground">Courier consignment</h2>
            <Row label="Courier" value={shipment.courier} />
            <Row label="Consignment" value={shipment.consignment_id} />
            <Row label="Tracking" value={shipment.tracking_code} />
            <Row label="Status" value={shipment.status} />
            <Row label="COD to collect" value={shipment.cod_amount} />
        </div>
    );
}

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex justify-between gap-4 py-1 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value ?? '—'}</span>
        </div>
    );
}

const PAYMENT_STATUS_STYLES: Record<string, string> = {
    success: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    failed: 'bg-red-500/15 text-red-600 dark:text-red-400',
    cancelled: 'bg-slate-500/15 text-slate-600 dark:text-slate-300',
    pending: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
};

function PaymentsCard({
    order,
    canManage,
    direction,
    setDirection,
}: {
    order: Order;
    canManage: boolean;
    direction: string;
    setDirection: (d: string) => void;
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <h2 className="mb-3 text-sm font-medium text-muted-foreground">Payments</h2>

            {order.payments.length === 0 ? (
                <p className="text-sm text-muted-foreground">No payments recorded yet.</p>
            ) : (
                <ul className="space-y-2">
                    {order.payments.map((p) => (
                        <li key={p.id} className="flex items-start justify-between gap-3 border-b pb-2 text-sm last:border-0">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {p.direction === 'debit' ? '− ' : '+ '}
                                        {p.amount}
                                    </span>
                                    <span
                                        className={`rounded px-1.5 py-0.5 text-[11px] font-medium ${PAYMENT_STATUS_STYLES[p.status] ?? 'bg-muted text-muted-foreground'}`}
                                    >
                                        {p.status}
                                    </span>
                                    <span className="text-xs text-muted-foreground capitalize">
                                        {p.gateway}
                                        {p.type === 'manual' ? ' · manual' : ''}
                                    </span>
                                </div>
                                {p.note && <p className="mt-0.5 text-xs text-muted-foreground">{p.note}</p>}
                            </div>
                            <span className="shrink-0 text-xs text-muted-foreground">{p.at}</span>
                        </li>
                    ))}
                </ul>
            )}

            {canManage && (
                <div className="mt-4 border-t pt-3">
                    <p className="mb-2 text-xs text-muted-foreground">
                        Adjust payment — adds a new ledger entry, never changes the customer’s original payment.
                    </p>
                    <Form method="post" action={`/admin/orders/${order.id}/payments`} options={{ preserveScroll: true }} resetOnSuccess>
                        {({ processing, errors }) => (
                            <div className="space-y-2">
                                <div className="flex gap-2">
                                    <select
                                        name="direction"
                                        value={direction}
                                        onChange={(e) => setDirection(e.target.value)}
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                    >
                                        <option value="credit">Payment received</option>
                                        <option value="debit">Refund / reduce</option>
                                    </select>
                                    <input
                                        name="amount"
                                        type="number"
                                        min={1}
                                        step={1}
                                        placeholder="Amount (৳)"
                                        className="h-9 w-32 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                    />
                                </div>
                                <InputError message={errors.amount} />
                                <input
                                    name="note"
                                    type="text"
                                    maxLength={255}
                                    placeholder="Note (required) — e.g. bKash received, partial refund"
                                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                />
                                <InputError message={errors.note} />
                                <Button type="submit" variant="outline" className="w-full" disabled={processing}>
                                    {direction === 'debit' ? 'Record refund' : 'Record payment'}
                                </Button>
                            </div>
                        )}
                    </Form>
                </div>
            )}
        </div>
    );
}

export default function OrderShow({
    order,
    nextStatuses,
    pendingReasons,
    canManagePayments,
    courierStats,
}: {
    order: Order;
    nextStatuses: string[];
    pendingReasons: string[];
    canManagePayments: boolean;
    courierStats: CourierStats | null;
}) {
    const [reason, setReason] = useState(order.pending_reason);
    const [direction, setDirection] = useState('credit');
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
                                <a href={`/admin/orders/${order.id}/label`}>
                                    <Ticket className="size-4" /> Shipping label
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
                                <Row label="Advance paid" value={order.advance_paid} />
                                <div className="flex justify-between gap-4 border-t pt-2 text-sm font-semibold">
                                    <span>Due (COD)</span>
                                    <span className="text-right">{order.due}</span>
                                </div>
                            </div>
                        </div>

                        <PaymentsCard order={order} canManage={canManagePayments} direction={direction} setDirection={setDirection} />
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

                        {order.shipment && <ShipmentCard shipment={order.shipment} />}

                        {courierStats && <CourierRiskCard stats={courierStats} />}
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
