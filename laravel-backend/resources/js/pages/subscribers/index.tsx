import { Head } from '@inertiajs/react';
import { Download, Mail } from 'lucide-react';
import { DataTable } from '@/components/admin/data-table';
import type { Column } from '@/components/admin/data-table';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type Subscriber = {
    id: number;
    email: string;
    source: string | null;
    at: string | null;
};

export default function SubscribersIndex({
    subscribers,
    total,
}: {
    subscribers: Subscriber[];
    total: number;
}) {
    const columns: Column<Subscriber>[] = [
        { key: 'email', header: 'Email', cell: (s) => <span className="font-medium">{s.email}</span> },
        {
            key: 'source',
            header: 'Source',
            cell: (s) => <span className="text-muted-foreground">{s.source ?? '—'}</span>,
        },
        { key: 'at', header: 'Subscribed', cell: (s) => <span className="text-muted-foreground">{s.at}</span> },
    ];

    const mobileCard = (s: Subscriber) => (
        <div className="space-y-0.5">
            <div className="font-medium break-all">{s.email}</div>
            <div className="text-xs text-muted-foreground">
                {s.source ?? '—'} · {s.at}
            </div>
        </div>
    );

    return (
        <>
            <Head title="Subscriptions" />
            <div className="mx-auto w-full max-w-4xl p-4">
                <PageHeader
                    title="Subscriptions"
                    description={`${total} newsletter subscriber${total === 1 ? '' : 's'}.`}
                    actions={
                        total > 0 ? (
                            <Button asChild variant="outline">
                                <a href="/admin/subscribers/export">
                                    <Download className="size-4" /> Export CSV
                                </a>
                            </Button>
                        ) : undefined
                    }
                />

                {subscribers.length === 0 ? (
                    <EmptyState icon={Mail} title="No subscribers yet." />
                ) : (
                    <DataTable
                        columns={columns}
                        rows={subscribers}
                        rowKey={(s) => s.id}
                        renderMobileCard={mobileCard}
                    />
                )}
            </div>
        </>
    );
}

SubscribersIndex.layout = {
    breadcrumbs: [{ title: 'Subscriptions', href: '/admin/subscribers' }],
};
