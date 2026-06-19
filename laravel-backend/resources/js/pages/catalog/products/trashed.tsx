import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ImageOff, RotateCcw, Trash2 } from 'lucide-react';
import {  DataTable } from '@/components/admin/data-table';
import type {Column} from '@/components/admin/data-table';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type TrashedProduct = {
    id: number;
    title: string;
    sku: string;
    category: string | null;
    deleted_at: string | null;
    main_image_url: string | null;
};

function Thumb({ url, alt }: { url: string | null; alt: string }) {
    if (!url) {
        return (
            <div className="flex size-10 items-center justify-center rounded-md border bg-muted text-muted-foreground">
                <ImageOff className="size-4" />
            </div>
        );
    }

    return <img src={url} alt={alt} className="size-10 rounded-md border bg-white object-cover" />;
}

export default function ProductsTrashed({ products }: { products: TrashedProduct[] }) {
    const restore = (row: TrashedProduct) =>
        router.post(`/admin/catalog/products/${row.id}/restore`, {}, { preserveScroll: true });

    const purge = (row: TrashedProduct) =>
        router.delete(`/admin/catalog/products/${row.id}/force`, {
            preserveScroll: true,
            onBefore: () =>
                confirm(`Permanently delete “${row.title}”? This cannot be undone.`),
        });

    const RowActions = ({ row }: { row: TrashedProduct }) => (
        <div className="flex justify-end gap-1">
            <Button variant="ghost" size="icon" aria-label="Restore" onClick={() => restore(row)}>
                <RotateCcw className="size-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                aria-label="Delete permanently"
                onClick={() => purge(row)}
            >
                <Trash2 className="size-4 text-destructive" />
            </Button>
        </div>
    );

    const columns: Column<TrashedProduct>[] = [
        {
            key: 'title',
            header: 'Product',
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <Thumb url={row.main_image_url} alt={row.title} />
                    <div className="min-w-0">
                        <div className="truncate font-medium">{row.title}</div>
                        <div className="text-xs text-muted-foreground">{row.sku}</div>
                    </div>
                </div>
            ),
        },
        {
            key: 'category',
            header: 'Category',
            cell: (row) => <span className="text-muted-foreground">{row.category ?? '—'}</span>,
        },
        {
            key: 'deleted_at',
            header: 'Deleted',
            cell: (row) => (
                <span className="text-muted-foreground">{row.deleted_at ?? '—'}</span>
            ),
        },
        { key: 'actions', header: 'Actions', align: 'right', cell: (row) => <RowActions row={row} /> },
    ];

    const mobileCard = (row: TrashedProduct) => (
        <div className="flex items-center gap-3">
            <Thumb url={row.main_image_url} alt={row.title} />
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{row.title}</div>
                <div className="mt-0.5 text-xs text-muted-foreground">
                    {row.category ?? '—'} · deleted {row.deleted_at ?? '—'}
                </div>
            </div>
            <RowActions row={row} />
        </div>
    );

    return (
        <>
            <Head title="Recycle bin — Products" />
            <div className="mx-auto w-full max-w-5xl p-4">
                <PageHeader
                    title="Recycle bin"
                    description="Deleted products. Restore them or remove permanently."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href="/admin/catalog/products">
                                <ArrowLeft className="size-4" /> Back to products
                            </Link>
                        </Button>
                    }
                />

                {products.length === 0 ? (
                    <EmptyState
                        icon={Trash2}
                        title="Recycle bin is empty."
                        description="Deleted products will appear here."
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        rows={products}
                        rowKey={(row) => row.id}
                        renderMobileCard={mobileCard}
                    />
                )}
            </div>
        </>
    );
}

ProductsTrashed.layout = {
    breadcrumbs: [
        { title: 'Products', href: '/admin/catalog/products' },
        { title: 'Recycle bin', href: '/admin/catalog/products/trashed' },
    ],
};
