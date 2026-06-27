import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { DataTable } from '@/components/admin/data-table';
import type { Column } from '@/components/admin/data-table';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type Page = {
    id: number;
    title: string;
    slug: string;
    is_published: boolean;
    position: number;
};

function StatusBadge({ published }: { published: boolean }) {
    return (
        <span
            className={`inline-block rounded-md px-2 py-0.5 text-xs font-medium ${
                published
                    ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                    : 'bg-muted text-muted-foreground'
            }`}
        >
            {published ? 'Published' : 'Draft'}
        </span>
    );
}

export default function PagesIndex({ pages }: { pages: Page[] }) {
    const remove = (p: Page) =>
        router.delete(`/admin/pages/${p.id}`, {
            preserveScroll: true,
            onBefore: () => confirm(`Delete “${p.title}”? This cannot be undone.`),
        });

    const RowActions = ({ p }: { p: Page }) => (
        <div className="flex justify-end gap-1">
            <Button variant="ghost" size="icon" asChild aria-label="Edit">
                <Link href={`/admin/pages/${p.id}/edit`}>
                    <Pencil className="size-4" />
                </Link>
            </Button>
            <Button variant="ghost" size="icon" aria-label="Delete" onClick={() => remove(p)}>
                <Trash2 className="size-4 text-destructive" />
            </Button>
        </div>
    );

    const columns: Column<Page>[] = [
        {
            key: 'title',
            header: 'Page',
            cell: (p) => (
                <div>
                    <div className="font-medium">{p.title}</div>
                    <div className="text-xs text-muted-foreground">/p/{p.slug}</div>
                </div>
            ),
        },
        {
            key: 'position',
            header: 'Order',
            cell: (p) => <span className="text-muted-foreground">{p.position}</span>,
        },
        {
            key: 'is_published',
            header: 'Status',
            cell: (p) => <StatusBadge published={p.is_published} />,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            cell: (p) => <RowActions p={p} />,
        },
    ];

    const mobileCard = (p: Page) => (
        <div className="flex items-center gap-3">
            <div className="flex size-10 items-center justify-center rounded-md border bg-muted text-muted-foreground">
                <FileText className="size-4" />
            </div>
            <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{p.title}</div>
                <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                    <StatusBadge published={p.is_published} />
                    <span>/p/{p.slug}</span>
                </div>
            </div>
            <RowActions p={p} />
        </div>
    );

    return (
        <>
            <Head title="Pages" />
            <div className="mx-auto w-full max-w-5xl p-4">
                <PageHeader
                    title="Pages"
                    description="Content pages (About us, Privacy policy, …) linked from the storefront footer."
                    actions={
                        <Button asChild>
                            <Link href="/admin/pages/create">
                                <Plus className="size-4" /> New page
                            </Link>
                        </Button>
                    }
                />

                {pages.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="No pages yet."
                        action={
                            <Button asChild>
                                <Link href="/admin/pages/create">
                                    <Plus className="size-4" /> Create your first page
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        rows={pages}
                        rowKey={(p) => p.id}
                        renderMobileCard={mobileCard}
                    />
                )}

                <p className="mt-4 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <ExternalLink className="size-3.5" />
                    Add a published page to the footer from Settings → Site &amp; branding →
                    Footer quick links, using the path <code>/p/your-slug</code>.
                </p>
            </div>
        </>
    );
}

PagesIndex.layout = {
    breadcrumbs: [{ title: 'Pages', href: '/admin/pages' }],
};
