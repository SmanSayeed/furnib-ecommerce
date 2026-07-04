import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, GripVertical, ImagePlus, X } from 'lucide-react';
import {  useRef, useState } from 'react';
import type {DragEvent} from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Category = { id: number; title: string };

type Zone = { id: number; name: string; base: string };

type ShippingCharge = { shipping_zone_id: number; extra_cost: number };

type GalleryImage = { id: number; url: string };

type Product = {
    id: number;
    category_id: number;
    title: string;
    slug: string | null;
    sku: string | null;
    details: string | null;
    product_video: string | null;
    price: number;
    discount_price: number | null;
    is_advance_payment: boolean;
    advance_payment_type: string | null;
    partial_amount_type: string | null;
    partial_amount: number | null;
    is_featured: boolean;
    is_new: boolean;
    position_order: number;
    product_status: 'draft' | 'published' | 'disabled';
    stock_amount: number;
    stock_status: boolean;
    shipping_charge_allowed: boolean;
    meta_title: string | null;
    meta_description: string | null;
    main_image_url: string | null;
    gallery: GalleryImage[];
    shipping_charges: ShippingCharge[];
};

const MAX_GALLERY = 6;

type GalleryItem =
    | { kind: 'existing'; id: number; url: string }
    | { kind: 'new'; file: File; url: string };

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';
const TEXTAREA_CLASS = 'rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs';

export default function ProductForm({
    product,
    categories,
    zones,
}: {
    product: Product | null;
    categories: Category[];
    zones: Zone[];
}) {
    const editing = Boolean(product);

    const { data, setData, post, processing, errors, transform } = useForm({
        category_id: product ? String(product.category_id) : '',
        title: product?.title ?? '',
        slug: product?.slug ?? '',
        sku: product?.sku ?? '',
        details: product?.details ?? '',
        price: product ? String(product.price) : '',
        discount_price: product?.discount_price != null ? String(product.discount_price) : '',
        is_advance_payment: product?.is_advance_payment ?? false,
        advance_payment_type: product?.advance_payment_type ?? '',
        partial_amount_type: product?.partial_amount_type ?? '',
        // "amount" is persisted as whole-taka paisa; show it back in taka. A
        // "percentage" keeps its raw percent value.
        partial_amount:
            product?.partial_amount != null
                ? String(
                      product.partial_amount_type === 'amount'
                          ? Math.round(product.partial_amount / 100)
                          : product.partial_amount,
                  )
                : '',
        is_featured: product?.is_featured ?? false,
        is_new: product?.is_new ?? false,
        position_order: String(product?.position_order ?? 0),
        product_status: product?.product_status ?? 'draft',
        stock_amount: String(product?.stock_amount ?? 0),
        stock_status: product?.stock_status ?? true,
        shipping_charge_allowed: product?.shipping_charge_allowed ?? true,
        meta_title: product?.meta_title ?? '',
        meta_description: product?.meta_description ?? '',
        main_image: null as File | null,
    });

    // gallery_new is injected at submit-time via transform, so it isn't part of
    // the typed form data — read its validation error defensively.
    const galleryError = (errors as Record<string, string | undefined>).gallery_new;

    const [mainPreview, setMainPreview] = useState<string | null>(product?.main_image_url ?? null);
    const [gallery, setGallery] = useState<GalleryItem[]>(
        product?.gallery.map((g) => ({ kind: 'existing', id: g.id, url: g.url })) ?? [],
    );
    const dragIndex = useRef<number | null>(null);

    // Per-zone extra shipping charge (display ৳), keyed by zone id. Submitted as
    // the `shipping_charges` array at submit-time; blanks are dropped server-side.
    const [extraByZone, setExtraByZone] = useState<Record<number, string>>(() => {
        const map: Record<number, string> = {};
        product?.shipping_charges?.forEach((c) => {
            map[c.shipping_zone_id] = String(c.extra_cost);
        });

        return map;
    });

    const addFiles = (files: FileList | null) => {
        if (!files) {
return;
}

        const room = MAX_GALLERY - gallery.length;
        const next = Array.from(files)
            .slice(0, Math.max(0, room))
            .map((file): GalleryItem => ({ kind: 'new', file, url: URL.createObjectURL(file) }));
        setGallery((prev) => [...prev, ...next]);
    };

    const removeAt = (index: number) =>
        setGallery((prev) => {
            const item = prev[index];

            if (item?.kind === 'new') {
URL.revokeObjectURL(item.url);
}

            return prev.filter((_, i) => i !== index);
        });

    const move = (from: number, to: number) =>
        setGallery((prev) => {
            if (to < 0 || to >= prev.length) {
return prev;
}

            const next = [...prev];
            const [moved] = next.splice(from, 1);
            next.splice(to, 0, moved);

            return next;
        });

    const onDrop = (e: DragEvent, to: number) => {
        e.preventDefault();

        if (dragIndex.current !== null) {
move(dragIndex.current, to);
}

        dragIndex.current = null;
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const newFiles: File[] = [];
        const layout = gallery.map((item) => {
            if (item.kind === 'existing') {
return { type: 'existing', id: item.id };
}

            const index = newFiles.length;
            newFiles.push(item.file);

            return { type: 'new', index };
        });

        const bool = (v: boolean) => (v ? '1' : '0');

        // One entry per active zone so cleared values reach the server and
        // remove the row; blank extras are dropped server-side.
        const shippingCharges = zones.map((z) => ({
            shipping_zone_id: z.id,
            extra_cost: extraByZone[z.id] ?? '',
        }));

        transform((current) => ({
            ...current,
            is_advance_payment: bool(current.is_advance_payment as boolean),
            is_featured: bool(current.is_featured as boolean),
            is_new: bool(current.is_new as boolean),
            stock_status: bool(current.stock_status as boolean),
            shipping_charge_allowed: bool(current.shipping_charge_allowed as boolean),
            gallery_new: newFiles,
            gallery_layout: JSON.stringify(layout),
            shipping_charges: shippingCharges,
            ...(editing ? { _method: 'put' } : {}),
        }));

        post(editing ? `/admin/catalog/products/${product!.id}` : '/admin/catalog/products', {
            forceFormData: true,
            preserveScroll: true,
            // On validation failure the save is rejected and the product is left
            // unchanged — scroll the error summary into view so it never looks
            // like the form silently "reverted" the values.
            onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
        });
    };

    // Live guard: a discount must stay below the price. Surfaced inline (and the
    // server enforces the same rule) so lowering the price under an old discount
    // is caught immediately instead of silently failing the save.
    const priceNum = parseFloat(data.price);
    const discountNum = parseFloat(data.discount_price);
    const discountTooHigh =
        Number.isFinite(priceNum) &&
        Number.isFinite(discountNum) &&
        discountNum >= priceNum;

    const errorList = Object.entries(errors as Record<string, string | undefined>).filter(
        ([, message]) => Boolean(message),
    );

    return (
        <>
            <Head title={editing ? 'Edit product' : 'New product'} />
            <form onSubmit={submit} className="mx-auto w-full max-w-3xl p-4 pb-24">
                <div className="mb-4 flex items-center gap-2">
                    <Button variant="ghost" size="icon" asChild aria-label="Back">
                        <Link href="/admin/catalog/products">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-lg font-semibold">
                        {editing ? 'Edit product' : 'New product'}
                    </h1>
                </div>

                {errorList.length > 0 && (
                    <div
                        role="alert"
                        className="mb-4 rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive"
                    >
                        <p className="font-semibold">
                            Nothing was saved — please fix the following:
                        </p>
                        <ul className="mt-2 list-inside list-disc space-y-1">
                            {errorList.map(([field, message]) => (
                                <li key={field}>{message}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Basics */}
                <section className="mb-4 space-y-4 rounded-xl border bg-card p-4 md:p-6">
                    <h2 className="text-sm font-medium text-muted-foreground">Basics</h2>
                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            required
                            placeholder="e.g. Oak Dining Chair"
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="category_id">
                                Category <span className="text-destructive">*</span>
                            </Label>
                            <select
                                id="category_id"
                                value={data.category_id}
                                onChange={(e) => setData('category_id', e.target.value)}
                                required
                                className={SELECT_CLASS}
                            >
                                <option value="" disabled>
                                    Select a category
                                </option>
                                {categories.map((c) => (
                                    <option key={c.id} value={String(c.id)}>
                                        {c.title}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.category_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sku">SKU (optional)</Label>
                            <Input
                                id="sku"
                                value={data.sku}
                                onChange={(e) => setData('sku', e.target.value)}
                                placeholder="auto-generated"
                            />
                            <InputError message={errors.sku} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="slug">Slug (optional)</Label>
                        <Input
                            id="slug"
                            value={data.slug}
                            onChange={(e) => setData('slug', e.target.value)}
                            placeholder="auto from title"
                        />
                        <InputError message={errors.slug} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="details">Details</Label>
                        <textarea
                            id="details"
                            rows={4}
                            value={data.details}
                            onChange={(e) => setData('details', e.target.value)}
                            className={TEXTAREA_CLASS}
                            placeholder="Description shown on the product"
                        />
                        <InputError message={errors.details} />
                    </div>
                </section>

                {/* Media */}
                <section className="mb-4 space-y-4 rounded-xl border bg-card p-4 md:p-6">
                    <h2 className="text-sm font-medium text-muted-foreground">Media</h2>

                    <div className="grid gap-2">
                        <Label htmlFor="main_image">Main image</Label>
                        <p className="text-xs text-muted-foreground">
                            Recommended 1080×1080 px (1:1 square). PNG/JPG/WebP/AVIF, max 20 MB. Shown in full (no crop) on a neutral background, so any aspect works — square looks best.
                        </p>
                        {mainPreview && (
                            <img
                                src={mainPreview}
                                alt="Main"
                                className="h-32 w-32 rounded-md border bg-white object-contain"
                            />
                        )}
                        <Input
                            id="main_image"
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/avif"
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                setData('main_image', file);

                                if (file) {
setMainPreview(URL.createObjectURL(file));
}
                            }}
                        />
                        <InputError message={errors.main_image} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Gallery ({gallery.length}/{MAX_GALLERY})</Label>
                        <p className="text-xs text-muted-foreground">
                            Each 1080×1080 px (1:1 square), max 20 MB, up to {MAX_GALLERY} images. Drag
                            (desktop) or use the arrows to reorder — the first image leads the
                            product slider.
                        </p>
                        {gallery.length > 0 && (
                            <div className="flex flex-wrap gap-3">
                                {gallery.map((item, index) => (
                                    <div
                                        key={item.kind === 'existing' ? `e${item.id}` : `n${index}`}
                                        draggable
                                        onDragStart={() => (dragIndex.current = index)}
                                        onDragOver={(e) => e.preventDefault()}
                                        onDrop={(e) => onDrop(e, index)}
                                        className="group relative size-24 overflow-hidden rounded-md border bg-white"
                                    >
                                        <img
                                            src={item.url}
                                            alt=""
                                            className="size-full object-contain"
                                        />
                                        <span className="absolute top-1 left-1 hidden rounded bg-black/40 p-0.5 text-white group-hover:block">
                                            <GripVertical className="size-3" />
                                        </span>
                                        <button
                                            type="button"
                                            aria-label="Remove image"
                                            onClick={() => removeAt(index)}
                                            className="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white hover:bg-destructive"
                                        >
                                            <X className="size-3" />
                                        </button>
                                        <div className="absolute inset-x-0 bottom-0 flex justify-between bg-black/40 px-1 py-0.5 opacity-0 group-hover:opacity-100">
                                            <button
                                                type="button"
                                                aria-label="Move left"
                                                disabled={index === 0}
                                                onClick={() => move(index, index - 1)}
                                                className="text-white disabled:opacity-30"
                                            >
                                                <ArrowLeft className="size-3" />
                                            </button>
                                            <button
                                                type="button"
                                                aria-label="Move right"
                                                disabled={index === gallery.length - 1}
                                                onClick={() => move(index, index + 1)}
                                                className="text-white disabled:opacity-30"
                                            >
                                                <ArrowRight className="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                        {gallery.length < MAX_GALLERY && (
                            <label className="flex w-fit cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground hover:bg-accent">
                                <ImagePlus className="size-4" /> Add images
                                <input
                                    type="file"
                                    multiple
                                    accept="image/png,image/jpeg,image/webp,image/avif"
                                    className="sr-only"
                                    onChange={(e) => {
                                        addFiles(e.target.files);
                                        e.target.value = '';
                                    }}
                                />
                            </label>
                        )}
                        <InputError message={galleryError} />
                    </div>
                </section>

                {/* Pricing & stock */}
                <section className="mb-4 space-y-4 rounded-xl border bg-card p-4 md:p-6">
                    <h2 className="text-sm font-medium text-muted-foreground">Pricing & stock</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="price">Price (৳)</Label>
                            <Input
                                id="price"
                                type="number"
                                step="0.01"
                                min={0}
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                                required
                            />
                            <InputError message={errors.price} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="discount_price">Discounted price (৳)</Label>
                            <Input
                                id="discount_price"
                                type="number"
                                step="0.01"
                                min={0}
                                value={data.discount_price}
                                onChange={(e) => setData('discount_price', e.target.value)}
                                placeholder="optional"
                            />
                            {discountTooHigh && !errors.discount_price && (
                                <p className="text-xs text-destructive">
                                    Discounted price must be lower than the price (৳{data.price}).
                                    Clear it or lower it to save.
                                </p>
                            )}
                            <InputError message={errors.discount_price} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="stock_amount">Stock amount</Label>
                            <Input
                                id="stock_amount"
                                type="number"
                                min={0}
                                value={data.stock_amount}
                                onChange={(e) => setData('stock_amount', e.target.value)}
                            />
                            <InputError message={errors.stock_amount} />
                        </div>
                        <div className="flex items-center gap-2 pt-6">
                            <Checkbox
                                id="stock_status"
                                checked={data.stock_status}
                                onCheckedChange={(v) => setData('stock_status', v === true)}
                            />
                            <Label htmlFor="stock_status">In stock</Label>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_advance_payment"
                            checked={data.is_advance_payment}
                            onCheckedChange={(v) => setData('is_advance_payment', v === true)}
                        />
                        <Label htmlFor="is_advance_payment">Requires advance payment</Label>
                    </div>
                    {data.is_advance_payment && (() => {
                        // Partial fields only apply to a "partial" advance. For
                        // "full" (or no type selected) they are disabled + cleared.
                        const partialEnabled = data.advance_payment_type === 'partial';

                        return (
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="advance_payment_type">Advance type</Label>
                                    <select
                                        id="advance_payment_type"
                                        value={data.advance_payment_type}
                                        onChange={(e) => {
                                            const next = e.target.value;
                                            setData('advance_payment_type', next);

                                            // Clear partial fields unless switching
                                            // to "partial".
                                            if (next !== 'partial') {
                                                setData('partial_amount_type', '');
                                                setData('partial_amount', '');
                                            }
                                        }}
                                        className={SELECT_CLASS}
                                    >
                                        <option value="">—</option>
                                        <option value="full">Full</option>
                                        <option value="partial">Partial</option>
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="partial_amount_type">Partial as</Label>
                                    <select
                                        id="partial_amount_type"
                                        value={data.partial_amount_type}
                                        disabled={!partialEnabled}
                                        onChange={(e) => {
                                            const next = e.target.value;
                                            setData('partial_amount_type', next);

                                            if (next === 'shipping') {
                                                setData('partial_amount', '');
                                            }
                                        }}
                                        className={`${SELECT_CLASS} disabled:cursor-not-allowed disabled:opacity-50`}
                                    >
                                        <option value="">—</option>
                                        <option value="percentage">Percentage</option>
                                        <option value="amount">Fixed amount</option>
                                        <option value="shipping">Shipping charge</option>
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="partial_amount">
                                        Partial value
                                        {data.partial_amount_type === 'percentage' && ' (%)'}
                                        {data.partial_amount_type === 'amount' && ' (৳, whole taka)'}
                                    </Label>
                                    <Input
                                        id="partial_amount"
                                        type="number"
                                        min={0}
                                        step={1}
                                        value={
                                            data.partial_amount_type === 'shipping'
                                                ? ''
                                                : data.partial_amount
                                        }
                                        disabled={
                                            !partialEnabled ||
                                            data.partial_amount_type === 'shipping'
                                        }
                                        onChange={(e) => setData('partial_amount', e.target.value)}
                                    />
                                    {data.partial_amount_type === 'percentage' && (
                                        <p className="text-xs text-muted-foreground">
                                            Advance = this % of the product price, rounded to the
                                            nearest whole taka (no poysha).
                                        </p>
                                    )}
                                    {data.partial_amount_type === 'amount' && (
                                        <p className="text-xs text-muted-foreground">
                                            Fixed advance in whole taka (৳), capped at the order
                                            total.
                                        </p>
                                    )}
                                    {data.partial_amount_type === 'shipping' && (
                                        <p className="text-xs text-muted-foreground">
                                            Advance = the customer&apos;s selected delivery
                                            charge at checkout (Inside / Outside Dhaka).
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })()}

                    <div className="border-t pt-4">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="shipping_charge_allowed"
                                checked={data.shipping_charge_allowed}
                                onCheckedChange={(v) =>
                                    setData('shipping_charge_allowed', v === true)
                                }
                            />
                            <Label htmlFor="shipping_charge_allowed">
                                Charge delivery for this product
                            </Label>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            On by default. Turn off for{' '}
                            <strong>free delivery</strong> — no zone charge and no
                            per-product extra are applied, and the order shows shipping
                            as free.
                        </p>
                    </div>
                </section>

                {/* Shipping charges */}
                {zones.length > 0 && data.shipping_charge_allowed && (
                    <section className="mb-4 space-y-4 rounded-xl border bg-card p-4 md:p-6">
                        <div>
                            <h2 className="text-sm font-medium text-muted-foreground">
                                Shipping charges (optional)
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Extra delivery cost for this product, <strong>per unit</strong>,
                                added on top of each zone&apos;s base charge. Leave blank for no
                                extra. Example: Inside Dhaka base ৳80 + ৳20 here = ৳100/unit.
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {zones.map((zone, idx) => {
                                const errs = errors as Record<string, string | undefined>;
                                const err =
                                    errs[`shipping_charges.${idx}.extra_cost`] ??
                                    errs[`shipping_charges.${idx}.shipping_zone_id`];

                                return (
                                    <div key={zone.id} className="grid gap-2">
                                        <Label htmlFor={`zone_${zone.id}`}>
                                            {zone.name}{' '}
                                            <span className="text-xs text-muted-foreground">
                                                (base {zone.base})
                                            </span>
                                        </Label>
                                        <Input
                                            id={`zone_${zone.id}`}
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            value={extraByZone[zone.id] ?? ''}
                                            onChange={(e) =>
                                                setExtraByZone((prev) => ({
                                                    ...prev,
                                                    [zone.id]: e.target.value,
                                                }))
                                            }
                                            placeholder="extra ৳ (optional)"
                                        />
                                        <InputError message={err} />
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                )}

                {/* Visibility & SEO */}
                <section className="mb-4 space-y-4 rounded-xl border bg-card p-4 md:p-6">
                    <h2 className="text-sm font-medium text-muted-foreground">Visibility & SEO</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="product_status">Status</Label>
                            <select
                                id="product_status"
                                value={data.product_status}
                                onChange={(e) =>
                                    setData(
                                        'product_status',
                                        e.target.value as Product['product_status'],
                                    )
                                }
                                className={SELECT_CLASS}
                            >
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="disabled">Disabled</option>
                            </select>
                            <InputError message={errors.product_status} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="position_order">Sort order</Label>
                            <Input
                                id="position_order"
                                type="number"
                                min={0}
                                value={data.position_order}
                                onChange={(e) => setData('position_order', e.target.value)}
                            />
                            <InputError message={errors.position_order} />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-6">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_featured"
                                checked={data.is_featured}
                                onCheckedChange={(v) => setData('is_featured', v === true)}
                            />
                            <Label htmlFor="is_featured">Featured</Label>
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_new"
                                checked={data.is_new}
                                onCheckedChange={(v) => setData('is_new', v === true)}
                            />
                            <Label htmlFor="is_new">Mark as new</Label>
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="meta_title">Meta title (SEO)</Label>
                        <Input
                            id="meta_title"
                            value={data.meta_title}
                            onChange={(e) => setData('meta_title', e.target.value)}
                        />
                        <InputError message={errors.meta_title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="meta_description">Meta description (SEO)</Label>
                        <textarea
                            id="meta_description"
                            rows={2}
                            value={data.meta_description}
                            onChange={(e) => setData('meta_description', e.target.value)}
                            className={TEXTAREA_CLASS}
                        />
                        <InputError message={errors.meta_description} />
                    </div>
                </section>

                <div className="sticky bottom-0 mt-4 flex items-center justify-end gap-2 border-t bg-background/95 py-3 backdrop-blur">
                    <Button variant="outline" asChild>
                        <Link href="/admin/catalog/products">Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {editing ? 'Save changes' : 'Create product'}
                    </Button>
                </div>
            </form>
        </>
    );
}

ProductForm.layout = {
    breadcrumbs: [
        { title: 'Products', href: '/admin/catalog/products' },
        { title: 'Edit', href: '#' },
    ],
};
