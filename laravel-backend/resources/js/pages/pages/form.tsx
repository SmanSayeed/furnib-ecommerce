import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { RichTextEditor } from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PageData = {
    id: number;
    slug: string;
    title: string;
    body: string | null;
    is_published: boolean;
    position: number;
} | null;

export default function PageForm({ page }: { page: PageData }) {
    const isEdit = page !== null;

    const form = useForm({
        title: page?.title ?? '',
        slug: page?.slug ?? '',
        body: page?.body ?? '',
        is_published: page?.is_published ?? false,
        position: page?.position ?? 0,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (isEdit && page) {
            form.put(`/admin/pages/${page.id}`);
        } else {
            form.post('/admin/pages');
        }
    };

    return (
        <>
            <Head title={isEdit ? 'Edit page' : 'New page'} />
            <div className="mx-auto w-full max-w-3xl p-4">
                <PageHeader
                    title={isEdit ? 'Edit page' : 'New page'}
                    description="Rich content shown at /p/{slug} and linkable from the footer."
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            required
                            placeholder="Privacy Policy"
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="slug">
                            Slug <span className="text-muted-foreground">(optional)</span>
                        </Label>
                        <Input
                            id="slug"
                            value={form.data.slug}
                            onChange={(e) => form.setData('slug', e.target.value)}
                            placeholder="privacy-policy"
                        />
                        <p className="text-xs text-muted-foreground">
                            Leave blank to generate from the title. Lowercase letters, numbers
                            and hyphens only.
                        </p>
                        <InputError message={form.errors.slug} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Content</Label>
                        <RichTextEditor
                            value={form.data.body}
                            onChange={(html) => form.setData('body', html)}
                        />
                        <InputError message={form.errors.body} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="position">Footer order</Label>
                            <Input
                                id="position"
                                type="number"
                                min={0}
                                value={form.data.position}
                                onChange={(e) =>
                                    form.setData('position', Number(e.target.value) || 0)
                                }
                            />
                            <InputError message={form.errors.position} />
                        </div>
                        <label className="flex items-center gap-2 self-end pb-2">
                            <input
                                type="checkbox"
                                checked={form.data.is_published}
                                onChange={(e) => form.setData('is_published', e.target.checked)}
                                className="size-4 accent-[#e85d1f]"
                            />
                            <span className="text-sm">Published (visible on the storefront)</span>
                        </label>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            {isEdit ? 'Save changes' : 'Create page'}
                        </Button>
                        {form.recentlySuccessful && (
                            <span className="text-sm text-green-600">Saved.</span>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}

PageForm.layout = {
    breadcrumbs: [
        { title: 'Pages', href: '/admin/pages' },
        { title: 'Edit', href: '#' },
    ],
};
