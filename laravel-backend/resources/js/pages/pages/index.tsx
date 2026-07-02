import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, FileText, Lock, Pencil, Plus, Trash2 } from 'lucide-react';
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
    is_system: boolean;
    show_in_footer: boolean;
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
            {p.is_system ? (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Protected page"
                    title="System page — cannot be deleted"
                    disabled
                >
                    <Lock className="size-4 text-muted-foreground" />
                </Button>
            ) : (
                <Button variant="ghost" size="icon" aria-label="Delete" onClick={() => remove(p)}>
                    <Trash2 className="size-4 text-destructive" />
                </Button>
            )}
        </div>
    );

    const SystemBadge = () => (
        <span className="inline-flex items-center gap-1 rounded-md bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">
            <Lock className="size-3" /> System
        </span>
    );

    const HiddenBadge = () => (
        <span
            className="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
            title="Published but hidden from the storefront footer"
        >
            Not in footer
        </span>
    );

    const columns: Column<Page>[] = [
        {
            key: 'title',
            header: 'Page',
            cell: (p) => (
                <div>
                    <div className="flex flex-wrap items-center gap-2 font-medium">
                        {p.title}
                        {p.is_system && <SystemBadge />}
                        {p.is_published && !p.show_in_footer && <HiddenBadge />}
                    </div>
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
                <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <StatusBadge published={p.is_published} />
                    {p.is_system && <SystemBadge />}
                    {p.is_published && !p.show_in_footer && <HiddenBadge />}
                    <span>/p/{p.slug}</span>
                </div>
            </div>
            <RowActions p={p} />
        </div>
    );

    return (
        <>
            <Head title="Footer pages" />
            <div className="mx-auto w-full max-w-5xl p-4">
                <PageHeader
                    title="Footer pages"
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
                    Every published page shows in the storefront footer automatically. Hide or
                    re-add individual pages from Settings → Footer details → Footer pages.
                </p>
            </div>
        </>
    );
}

PagesIndex.layout = {
    breadcrumbs: [{ title: 'Footer pages', href: '/admin/pages' }],
};
