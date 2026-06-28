import { Head } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import { DataTable } from '@/components/admin/data-table';
import type { Column } from '@/components/admin/data-table';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';

type Activity = {
    id: number;
    log_name: string | null;
    event: string | null;
    description: string | null;
    subject: string | null;
    causer: string;
    at: string | null;
};

export default function AuditLog({ activities }: { activities: Activity[] }) {
    const columns: Column<Activity>[] = [
        { key: 'at', header: 'When', cell: (a) => <span className="text-muted-foreground">{a.at}</span> },
        { key: 'causer', header: 'Who', cell: (a) => <span className="font-medium">{a.causer}</span> },
        {
            key: 'event',
            header: 'Event',
            cell: (a) => (
                <span className="inline-block rounded-md bg-muted px-2 py-0.5 text-xs font-medium capitalize">
                    {a.event ?? a.log_name ?? '—'}
                </span>
            ),
        },
        {
            key: 'subject',
            header: 'Subject',
            cell: (a) => <span className="text-muted-foreground">{a.subject ?? '—'}</span>,
        },
        {
            key: 'description',
            header: 'Description',
            cell: (a) => <span className="text-muted-foreground">{a.description ?? '—'}</span>,
        },
    ];

    const mobileCard = (a: Activity) => (
        <div className="space-y-1">
            <div className="flex items-center justify-between">
                <span className="font-medium">{a.causer}</span>
                <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium capitalize">
                    {a.event ?? a.log_name ?? '—'}
                </span>
            </div>
            <div className="text-xs text-muted-foreground">
                {a.subject ?? '—'} · {a.description ?? ''}
            </div>
            <div className="text-xs text-muted-foreground">{a.at}</div>
        </div>
    );

    return (
        <>
            <Head title="Audit log" />
            <div className="mx-auto w-full max-w-6xl p-4">
                <PageHeader
                    title="Audit log"
                    description="Recent admin activity across the system (read-only)."
                />

                {activities.length === 0 ? (
                    <EmptyState icon={ScrollText} title="No activity recorded yet." />
                ) : (
                    <DataTable
                        columns={columns}
                        rows={activities}
                        rowKey={(a) => a.id}
                        renderMobileCard={mobileCard}
                    />
                )}
            </div>
        </>
    );
}

AuditLog.layout = {
    breadcrumbs: [{ title: 'Audit log', href: '/admin/audit-logs' }],
};
