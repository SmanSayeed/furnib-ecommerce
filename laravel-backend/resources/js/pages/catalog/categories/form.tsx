import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Category = {
    id: number;
    title: string;
    slug: string;
    details: string | null;
    status: boolean;
    position_order: number;
    meta_title: string | null;
    meta_description: string | null;
    header_url: string | null;
    header_mobile_url: string | null;
    thumbnail_url: string | null;
};

function ImageField({
    name,
    label,
    current,
    description,
}: {
    name: string;
    label: string;
    current: string | null;
    description?: string;
}) {
    const [preview, setPreview] = useState<string | null>(current);

    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            {description && (
                <p className="text-xs text-muted-foreground">{description}</p>
            )}
            {preview && (
                <img
                    src={preview}
                    alt={label}
                    className="h-24 w-full rounded-md border bg-white object-contain"
                />
            )}
            <Input
                id={name}
                name={name}
                type="file"
                accept="image/png,image/jpeg,image/webp,image/avif"
                onChange={(e) => {
                    const file = e.target.files?.[0];

                    if (file) {
setPreview(URL.createObjectURL(file));
}
                }}
            />
        </div>
    );
}

export default function CategoryForm({ category }: { category: Category | null }) {
    const editing = Boolean(category);
    const [status, setStatus] = useState<boolean>(category?.status ?? true);

    const action = editing
        ? `/admin/catalog/categories/${category!.id}`
        : '/admin/catalog/categories';

    return (
        <>
            <Head title={editing ? 'Edit category' : 'New category'} />
            {/*
             * Always POST — spoof PUT via `_method` when editing. A real PUT with
             * multipart/form-data is NOT parsed by PHP ($_POST/$_FILES stay empty),
             * which drops every field (title, images) and surfaces as a bogus
             * "title is required" error with the image never uploading. POST +
             * `_method` (same pattern as the product form) fixes both.
             */}
            <Form
                action={action}
                method="post"
                options={{ preserveScroll: true }}
                className="mx-auto w-full max-w-2xl p-4 pb-24"
            >
                {({ processing, errors }) => (
                    <>
                        <h1 className="mb-4 text-lg font-semibold">
                            {editing ? 'Edit category' : 'New category'}
                        </h1>

                        {editing && <input type="hidden" name="_method" value="put" />}
                        <input type="hidden" name="status" value={status ? '1' : '0'} />

                        <div className="space-y-6 rounded-xl border bg-card p-4 md:p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={category?.title ?? ''}
                                    required
                                    placeholder="e.g. Chairs"
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="slug">Slug (optional)</Label>
                                    <Input
                                        id="slug"
                                        name="slug"
                                        defaultValue={category?.slug ?? ''}
                                        placeholder="auto from title"
                                    />
                                    <InputError message={errors.slug} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="position_order">Sort order</Label>
                                    <Input
                                        id="position_order"
                                        name="position_order"
                                        type="number"
                                        min={0}
                                        defaultValue={category?.position_order ?? 0}
                                    />
                                    <InputError message={errors.position_order} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="details">Details</Label>
                                <textarea
                                    id="details"
                                    name="details"
                                    rows={3}
                                    defaultValue={category?.details ?? ''}
                                    className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs"
                                    placeholder="Short description shown on the category page"
                                />
                                <InputError message={errors.details} />
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="status"
                                    checked={status}
                                    onCheckedChange={(v) => setStatus(v === true)}
                                />
                                <Label htmlFor="status">Active (visible on storefront)</Label>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <ImageField
                                    name="header_image"
                                    label="Header image (desktop)"
                                    current={category?.header_url ?? null}
                                    description="Wide hero banner — recommended 1600×600 px. PNG/JPG/WebP/AVIF, max 20 MB."
                                />
                                <ImageField
                                    name="header_image_mobile"
                                    label="Header image (mobile)"
                                    current={category?.header_mobile_url ?? null}
                                    description="Optional mobile header (portrait). Falls back to the desktop header if empty. Max 20 MB."
                                />
                                <ImageField
                                    name="thumbnail_image"
                                    label="Thumbnail image"
                                    current={category?.thumbnail_url ?? null}
                                    description="Square card image — recommended 600×600 px. Max 20 MB."
                                />
                            </div>
                            <InputError message={errors.header_image} />
                            <InputError message={errors.header_image_mobile} />
                            <InputError message={errors.thumbnail_image} />

                            <div className="grid gap-2">
                                <Label htmlFor="meta_title">Meta title (SEO)</Label>
                                <Input
                                    id="meta_title"
                                    name="meta_title"
                                    defaultValue={category?.meta_title ?? ''}
                                />
                                <InputError message={errors.meta_title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="meta_description">Meta description (SEO)</Label>
                                <textarea
                                    id="meta_description"
                                    name="meta_description"
                                    rows={2}
                                    defaultValue={category?.meta_description ?? ''}
                                    className="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs"
                                />
                                <InputError message={errors.meta_description} />
                            </div>
                        </div>

                        <div className="sticky bottom-0 mt-4 flex items-center justify-end gap-2 border-t bg-background/95 py-3 backdrop-blur">
                            <Button variant="outline" asChild>
                                <Link href="/admin/catalog/categories">Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editing ? 'Save changes' : 'Create category'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

CategoryForm.layout = {
    breadcrumbs: [
        { title: 'Categories', href: '/admin/catalog/categories' },
        { title: 'Edit', href: '#' },
    ],
};
