import { Head, Link, router } from '@inertiajs/react';
import { FolderTree, Pencil, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Category = {
    id: number;
    title: string;
    slug: string;
    status: boolean;
    position_order: number;
    products_count: number;
    thumbnail_url: string | null;
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

function Thumb({ url, alt }: { url: string | null; alt: string }) {
    if (!url) {
        return (
            <div className="flex size-10 items-center justify-center rounded-md border bg-muted text-muted-foreground">
                <FolderTree className="size-4" />
            </div>
        );
    }

    return <img src={url} alt={alt} className="size-10 rounded-md border object-cover" />;
}

export default function CategoriesIndex({ categories }: { categories: Category[] }) {
    const remove = (c: Category) =>
        router.delete(`/admin/catalog/categories/${c.id}`, {
            preserveScroll: true,
            onBefore: () =>
                confirm(`Delete “${c.title}”? This can be restored later from the recycle bin.`),
        });

    return (
        <>
            <Head title="Categories" />
            <div className="mx-auto w-full max-w-5xl p-4">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-lg font-semibold">Categories</h1>
                        <p className="text-sm text-muted-foreground">
                            Storefront collections shown on the home page and menu.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/catalog/categories/create">
                            <Plus className="size-4" /> New category
                        </Link>
                    </Button>
                </div>

                {categories.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border bg-card p-12 text-center">
                        <FolderTree className="size-8 text-muted-foreground/50" />
                        <p className="text-sm text-muted-foreground">No categories yet.</p>
                        <Button asChild>
                            <Link href="/admin/catalog/categories/create">
                                <Plus className="size-4" /> Create your first category
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="rounded-xl border bg-card">
                        <table className="hidden w-full text-sm md:table">
                            <thead>
                                <tr className="border-b text-left text-xs text-muted-foreground">
                                    <th className="px-4 py-2 font-normal">Category</th>
                                    <th className="px-4 py-2 font-normal">Products</th>
                                    <th className="px-4 py-2 font-normal">Order</th>
                                    <th className="px-4 py-2 font-normal">Status</th>
                                    <th className="px-4 py-2 text-right font-normal">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {categories.map((c) => (
                                    <tr key={c.id} className="border-b last:border-0">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <Thumb url={c.thumbnail_url} alt={c.title} />
                                                <div>
                                                    <div className="font-medium">{c.title}</div>
                                                    <div className="text-xs text-muted-foreground">/{c.slug}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">{c.products_count}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{c.position_order}</td>
                                        <td className="px-4 py-3"><StatusBadge active={c.status} /></td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" asChild aria-label="Edit">
                                                    <Link href={`/admin/catalog/categories/${c.id}/edit`}>
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Delete"
                                                    onClick={() => remove(c)}
                                                >
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <div className="divide-y md:hidden">
                            {categories.map((c) => (
                                <div key={c.id} className="flex items-center gap-3 p-4">
                                    <Thumb url={c.thumbnail_url} alt={c.title} />
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">{c.title}</div>
                                        <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                            <StatusBadge active={c.status} />
                                            <span>{c.products_count} products</span>
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="icon" asChild aria-label="Edit">
                                        <Link href={`/admin/catalog/categories/${c.id}/edit`}>
                                            <Pencil className="size-4" />
                                        </Link>
                                    </Button>
                                    <Button variant="ghost" size="icon" aria-label="Delete" onClick={() => remove(c)}>
                                        <Trash2 className="size-4 text-destructive" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: '/admin/catalog/categories' }],
};
