import { Head, Link, router } from '@inertiajs/react';
import { FolderTree, Pencil, Plus, Trash2 } from 'lucide-react';
import {  DataTable } from '@/components/admin/data-table';
import type {Column} from '@/components/admin/data-table';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
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

    const RowActions = ({ c }: { c: Category }) => (
        <div className="flex justify-end gap-1">
            <Button variant="ghost" size="icon" asChild aria-label="Edit">
                <Link href={`/admin/catalog/categories/${c.id}/edit`}>
                    <Pencil className="size-4" />
                </Link>
            </Button>
            <Button variant="ghost" size="icon" aria-label="Delete" onClick={() => remove(c)}>
                <Trash2 className="size-4 text-destructive" />
            </Button>
        </div>
    );

    const columns: Column<Category>[] = [
        {
            key: 'title',
            header: 'Category',
            cell: (c) => (
                <div className="flex items-center gap-3">
                    <Thumb url={c.thumbnail_url} alt={c.title} />
                    <div>
                        <div className="font-medium">{c.title}</div>
                        <div className="text-xs text-muted-foreground">/{c.slug}</div>
                    </div>
                </div>
            ),
        },
        {
            key: 'products_count',
            header: 'Products',
            cell: (c) => <span className="text-muted-foreground">{c.products_count}</span>,
        },
        {
            key: 'position_order',
            header: 'Order',
            cell: (c) => <span className="text-muted-foreground">{c.position_order}</span>,
        },
        { key: 'status', header: 'Status', cell: (c) => <StatusBadge active={c.status} /> },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            cell: (c) => <RowActions c={c} />,
        },
    ];

    const mobileCard = (c: Category) => (
        <div className="flex items-center gap-3">
            <Thumb url={c.thumbnail_url} alt={c.title} />
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{c.title}</div>
                <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                    <StatusBadge active={c.status} />
                    <span>{c.products_count} products</span>
                </div>
            </div>
            <RowActions c={c} />
        </div>
    );

    return (
        <>
            <Head title="Categories" />
            <div className="mx-auto w-full max-w-5xl p-4">
                <PageHeader
                    title="Categories"
                    description="Storefront collections shown on the home page and menu."
                    actions={
                        <Button asChild>
                            <Link href="/admin/catalog/categories/create">
                                <Plus className="size-4" /> New category
                            </Link>
                        </Button>
                    }
                />

                {categories.length === 0 ? (
                    <EmptyState
                        icon={FolderTree}
                        title="No categories yet."
                        action={
                            <Button asChild>
                                <Link href="/admin/catalog/categories/create">
                                    <Plus className="size-4" /> Create your first category
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        rows={categories}
                        rowKey={(c) => c.id}
                        renderMobileCard={mobileCard}
                    />
                )}
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: '/admin/catalog/categories' }],
};
