import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type ZoneOption = { id: number; name: string; cost: string };

type ProductHit = {
    id: number;
    title: string;
    sku: string | null;
    stock_amount: number;
    in_stock: boolean;
    unit_price_minor: number;
    unit_price: string;
    regular_price: string;
    is_discounted: boolean;
};

type Line = {
    product_id: number;
    title: string;
    sku: string | null;
    qty: number;
    unit_price: string; // whole taka, editable
    regular_price: string;
};

type Props = { shippingZones: ZoneOption[]; paymentMethods: string[] };

const PAYMENT_METHOD_LABELS: Record<string, string> = {
    bkash: 'bKash',
    nagad: 'Nagad',
    rocket: 'Rocket',
    bank: 'Bank',
    cash: 'Cash',
    other: 'Other',
};

const inputClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring/50';

/** Pull the numeric taka out of a server-formatted money string like "৳1,000". */
function taka(formatted: string): number {
    return Number(formatted.replace(/[^0-9.]/g, '')) || 0;
}

export default function OrdersCreate({ shippingZones, paymentMethods }: Props) {
    const { data, setData, post, processing, errors, transform } = useForm({
        customer: { name: '', mobile: '', email: '' },
        address: '',
        shipping_zone_id: '',
        items: [] as Line[],
        discount: '',
        discount_note: '',
        shipping_override: '',
        advance_paid: '',
        advance_method: '',
        advance_note: '',
        confirm: false,
        send_sms: false,
    });

    // ── Product picker ────────────────────────────────────────────────────────
    const [query, setQuery] = useState('');
    const [hits, setHits] = useState<ProductHit[]>([]);
    const [searching, setSearching] = useState(false);
    const debounce = useRef<number | null>(null);

    useEffect(() => {
        if (debounce.current) {
            window.clearTimeout(debounce.current);
        }

        const q = query.trim();

        // All state updates happen inside the async timeout callback, never
        // synchronously in the effect body — no cascading renders.
        debounce.current = window.setTimeout(() => {
            if (q === '') {
                setHits([]);

                return;
            }

            setSearching(true);
            fetch(`/admin/orders/product-search?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((d: { products?: ProductHit[] }) => setHits(d.products ?? []))
                .catch(() => setHits([]))
                .finally(() => setSearching(false));
        }, q === '' ? 0 : 300);

        return () => {
            if (debounce.current) {
                window.clearTimeout(debounce.current);
            }
        };
    }, [query]);

    const addProduct = (p: ProductHit) => {
        setQuery('');
        setHits([]);
        // Bump the qty if already added.
        const existing = data.items.findIndex((l) => l.product_id === p.id);

        if (existing >= 0) {
            const items = [...data.items];
            items[existing] = { ...items[existing], qty: items[existing].qty + 1 };
            setData('items', items);

            return;
        }

        setData('items', [
            ...data.items,
            {
                product_id: p.id,
                title: p.title,
                sku: p.sku,
                qty: 1,
                unit_price: String(p.unit_price_minor / 100),
                regular_price: p.regular_price,
            },
        ]);
    };

    const updateLine = (i: number, patch: Partial<Line>) => {
        const items = [...data.items];
        items[i] = { ...items[i], ...patch };
        setData('items', items);
    };

    const removeLine = (i: number) => setData('items', data.items.filter((_, idx) => idx !== i));

    // ── Live totals (estimate; the server is authoritative on save) ───────────
    const subtotal = data.items.reduce((sum, l) => sum + l.qty * (Number(l.unit_price) || 0), 0);
    const discount = Number(data.discount) || 0;
    const zone = shippingZones.find((z) => String(z.id) === data.shipping_zone_id);
    const shipping =
        data.shipping_override !== '' ? Number(data.shipping_override) || 0 : zone ? taka(zone.cost) : 0;
    const total = Math.max(0, subtotal - discount + shipping);
    const money = (n: number) => `৳${n.toLocaleString('en-BD')}`;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // Send only what the server needs per line.
        transform((d) => ({
            ...d,
            items: d.items.map((l) => ({ product_id: l.product_id, qty: l.qty, unit_price: l.unit_price })),
        }));
        post('/admin/orders', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Create order" />
            <div className="mx-auto w-full max-w-5xl p-4 pb-28">
                <PageHeader
                    title="Create order"
                    description="Place an order on a customer's behalf and generate a pay link."
                    actions={
                        <Button asChild variant="outline">
                            <Link href="/admin/orders">
                                <ArrowLeft className="mr-1 h-4 w-4" /> Back
                            </Link>
                        </Button>
                    }
                />

                <form onSubmit={submit} className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        {/* Customer */}
                        <section className="rounded-xl border bg-card p-4">
                            <h2 className="mb-3 text-sm font-medium text-muted-foreground">Customer</h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs text-muted-foreground">Mobile *</label>
                                    <input
                                        className={inputClass}
                                        value={data.customer.mobile}
                                        onChange={(e) => setData('customer', { ...data.customer, mobile: e.target.value })}
                                        placeholder="01XXXXXXXXX"
                                    />
                                    <InputError message={errors['customer.mobile'] as string} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs text-muted-foreground">Name</label>
                                    <input
                                        className={inputClass}
                                        value={data.customer.name}
                                        onChange={(e) => setData('customer', { ...data.customer, name: e.target.value })}
                                    />
                                    <InputError message={errors['customer.name'] as string} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs text-muted-foreground">Email</label>
                                    <input
                                        className={inputClass}
                                        value={data.customer.email}
                                        onChange={(e) => setData('customer', { ...data.customer, email: e.target.value })}
                                    />
                                    <InputError message={errors['customer.email'] as string} />
                                </div>
                            </div>
                            <div className="mt-3">
                                <label className="mb-1 block text-xs text-muted-foreground">Delivery address *</label>
                                <textarea
                                    rows={2}
                                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                />
                                <InputError message={errors.address} />
                            </div>
                        </section>

                        {/* Products */}
                        <section className="rounded-xl border bg-card p-4">
                            <h2 className="mb-3 text-sm font-medium text-muted-foreground">Products</h2>
                            <div className="relative">
                                <div className="flex items-center gap-2 rounded-md border border-input px-3">
                                    <Search className="h-4 w-4 text-muted-foreground" />
                                    <input
                                        className="h-9 w-full bg-transparent text-sm outline-none"
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                        placeholder="Search product by name or SKU…"
                                    />
                                </div>
                                {(hits.length > 0 || searching) && (
                                    <div className="absolute z-10 mt-1 max-h-72 w-full overflow-auto rounded-md border bg-popover shadow-lg">
                                        {searching && <div className="px-3 py-2 text-sm text-muted-foreground">Searching…</div>}
                                        {hits.map((p) => (
                                            <button
                                                key={p.id}
                                                type="button"
                                                onClick={() => addProduct(p)}
                                                className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-accent"
                                            >
                                                <span className="min-w-0">
                                                    <span className="block truncate font-medium">{p.title}</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {p.sku ?? '—'} · {p.in_stock ? `${p.stock_amount} in stock` : 'out of stock'}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 text-right text-xs">
                                                    {p.is_discounted && <s className="mr-1 text-muted-foreground">{p.regular_price}</s>}
                                                    <span className="font-medium">{p.unit_price}</span>
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <InputError message={errors.items} />

                            {data.items.length > 0 && (
                                <div className="mt-3 space-y-2">
                                    {data.items.map((l, i) => (
                                        <div key={l.product_id} className="flex flex-wrap items-end gap-2 rounded-lg border p-2">
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate text-sm font-medium">{l.title}</div>
                                                <div className="text-xs text-muted-foreground">{l.sku ?? '—'}</div>
                                            </div>
                                            <div className="w-16">
                                                <label className="mb-1 block text-[11px] text-muted-foreground">Qty</label>
                                                <input
                                                    type="number"
                                                    min={1}
                                                    className={inputClass}
                                                    value={l.qty}
                                                    onChange={(e) => updateLine(i, { qty: Math.max(1, Number(e.target.value) || 1) })}
                                                />
                                            </div>
                                            <div className="w-28">
                                                <label className="mb-1 block text-[11px] text-muted-foreground">Unit price ৳</label>
                                                <input
                                                    type="number"
                                                    min={0}
                                                    className={inputClass}
                                                    value={l.unit_price}
                                                    onChange={(e) => updateLine(i, { unit_price: e.target.value })}
                                                />
                                            </div>
                                            <div className="w-24 text-right">
                                                <div className="text-[11px] text-muted-foreground">Line</div>
                                                <div className="text-sm font-medium">{money(l.qty * (Number(l.unit_price) || 0))}</div>
                                            </div>
                                            <Button type="button" variant="ghost" size="icon" onClick={() => removeLine(i)}>
                                                <Trash2 className="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>

                        {/* Delivery + adjustments */}
                        <section className="rounded-xl border bg-card p-4">
                            <h2 className="mb-3 text-sm font-medium text-muted-foreground">Delivery &amp; adjustments</h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs text-muted-foreground">Shipping zone</label>
                                    <select
                                        className={inputClass}
                                        value={data.shipping_zone_id}
                                        onChange={(e) => setData('shipping_zone_id', e.target.value)}
                                    >
                                        <option value="">No zone (free / pickup)</option>
                                        {shippingZones.map((z) => (
                                            <option key={z.id} value={z.id}>
                                                {z.name} — {z.cost}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.shipping_zone_id} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs text-muted-foreground">Shipping override ৳ (optional)</label>
                                    <input
                                        type="number"
                                        min={0}
                                        className={inputClass}
                                        value={data.shipping_override}
                                        onChange={(e) => setData('shipping_override', e.target.value)}
                                        placeholder="Leave blank to auto-calculate"
                                    />
                                    <InputError message={errors.shipping_override} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs text-muted-foreground">Discount ৳ (optional)</label>
                                    <input
                                        type="number"
                                        min={0}
                                        className={inputClass}
                                        value={data.discount}
                                        onChange={(e) => setData('discount', e.target.value)}
                                    />
                                    <InputError message={errors.discount} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs text-muted-foreground">Discount note</label>
                                    <input
                                        className={inputClass}
                                        value={data.discount_note}
                                        onChange={(e) => setData('discount_note', e.target.value)}
                                        placeholder="Required if discount > 0"
                                    />
                                    <InputError message={errors.discount_note} />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs text-muted-foreground">Advance collected ৳ (optional)</label>
                                    <input
                                        type="number"
                                        min={0}
                                        className={inputClass}
                                        value={data.advance_paid}
                                        onChange={(e) => setData('advance_paid', e.target.value)}
                                    />
                                    <InputError message={errors.advance_paid} />
                                </div>
                                {Number(data.advance_paid) > 0 && (
                                    <>
                                        <div>
                                            <label className="mb-1 block text-xs text-muted-foreground">Received via</label>
                                            <select
                                                className={inputClass}
                                                value={data.advance_method}
                                                onChange={(e) => setData('advance_method', e.target.value)}
                                            >
                                                <option value="" disabled>
                                                    Method (bKash / Nagad / …)
                                                </option>
                                                {paymentMethods.map((m) => (
                                                    <option key={m} value={m}>
                                                        {PAYMENT_METHOD_LABELS[m] ?? m}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.advance_method} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs text-muted-foreground">Transaction ID / ref</label>
                                            <input
                                                className={inputClass}
                                                value={data.advance_note}
                                                onChange={(e) => setData('advance_note', e.target.value)}
                                                placeholder="TrxID / bank ref / note"
                                            />
                                            <InputError message={errors.advance_note} />
                                        </div>
                                    </>
                                )}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Shipping shown is an estimate (zone base). The server recalculates the exact delivery — including
                                per-product charges — on save.
                            </p>
                        </section>
                    </div>

                    {/* Totals + submit */}
                    <div className="space-y-4">
                        <section className="rounded-xl border bg-card p-4 lg:sticky lg:top-4">
                            <h2 className="mb-3 text-sm font-medium text-muted-foreground">Summary</h2>
                            <dl className="space-y-1 text-sm">
                                <div className="flex justify-between"><dt className="text-muted-foreground">Subtotal</dt><dd>{money(subtotal)}</dd></div>
                                {discount > 0 && (
                                    <div className="flex justify-between text-emerald-600 dark:text-emerald-400">
                                        <dt>Discount</dt><dd>− {money(discount)}</dd>
                                    </div>
                                )}
                                <div className="flex justify-between"><dt className="text-muted-foreground">Shipping</dt><dd>{money(shipping)}</dd></div>
                                <div className="flex justify-between border-t pt-1 font-semibold"><dt>Total</dt><dd>{money(total)}</dd></div>
                            </dl>

                            <label className="mt-4 flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.confirm}
                                    onChange={(e) => setData('confirm', e.target.checked)}
                                />
                                Confirm order now (auto-book courier)
                            </label>
                            <label className="mt-2 flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.send_sms}
                                    onChange={(e) => setData('send_sms', e.target.checked)}
                                />
                                Send pay-link SMS to the customer
                            </label>

                            <Button type="submit" className="mt-4 w-full" disabled={processing || data.items.length === 0}>
                                {processing ? 'Creating…' : 'Create order'}
                            </Button>
                        </section>
                    </div>
                </form>
            </div>
        </>
    );
}

OrdersCreate.layout = {
    breadcrumbs: [
        { title: 'Orders', href: '/admin/orders' },
        { title: 'Create', href: '#' },
    ],
};
