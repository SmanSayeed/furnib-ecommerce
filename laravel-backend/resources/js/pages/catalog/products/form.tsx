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
    meta_title: string | null;
    meta_description: string | null;
    main_image_url: string | null;
    gallery: GalleryImage[];
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
}: {
    product: Product | null;
    categories: Category[];
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
        partial_amount: product?.partial_amount != null ? String(product.partial_amount) : '',
        is_featured: product?.is_featured ?? false,
        is_new: product?.is_new ?? false,
        position_order: String(product?.position_order ?? 0),
        product_status: product?.product_status ?? 'draft',
        stock_amount: String(product?.stock_amount ?? 0),
        stock_status: product?.stock_status ?? true,
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

        transform((current) => ({
            ...current,
            is_advance_payment: bool(current.is_advance_payment as boolean),
            is_featured: bool(current.is_featured as boolean),
            is_new: bool(current.is_new as boolean),
            stock_status: bool(current.stock_status as boolean),
            gallery_new: newFiles,
            gallery_layout: JSON.stringify(layout),
            ...(editing ? { _method: 'put' } : {}),
        }));

        post(editing ? `/admin/catalog/products/${product!.id}` : '/admin/catalog/products', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

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
                            <Label htmlFor="category_id">Category</Label>
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
                            Drag (desktop) or use the arrows to reorder. The first image leads the
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
                    {data.is_advance_payment && (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="advance_payment_type">Advance type</Label>
                                <select
                                    id="advance_payment_type"
                                    value={data.advance_payment_type}
                                    onChange={(e) =>
                                        setData('advance_payment_type', e.target.value)
                                    }
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
                                    onChange={(e) => {
                                        const next = e.target.value;
                                        setData('partial_amount_type', next);
                                        if (next === 'shipping') {
                                            setData('partial_amount', '');
                                        }
                                    }}
                                    className={SELECT_CLASS}
                                >
                                    <option value="">—</option>
                                    <option value="percentage">Percentage</option>
                                    <option value="amount">Fixed amount</option>
                                    <option value="shipping">Shipping charge</option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="partial_amount">Partial value</Label>
                                <Input
                                    id="partial_amount"
                                    type="number"
                                    min={0}
                                    value={
                                        data.partial_amount_type === 'shipping'
                                            ? ''
                                            : data.partial_amount
                                    }
                                    disabled={data.partial_amount_type === 'shipping'}
                                    onChange={(e) => setData('partial_amount', e.target.value)}
                                />
                                {data.partial_amount_type === 'shipping' && (
                                    <p className="text-xs text-muted-foreground">
                                        Advance = the customer&apos;s selected delivery
                                        charge at checkout (Inside / Outside Dhaka).
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </section>

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
