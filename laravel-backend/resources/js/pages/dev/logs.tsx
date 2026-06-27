import { Head } from '@inertiajs/react';
import { DevTabs } from '@/components/dev-tabs';
import Heading from '@/components/heading';

export default function DevLogs({
    available,
    path,
    lines,
}: {
    available: boolean;
    path: string;
    lines: string;
}) {
    return (
        <>
            <Head title="Developer — logs" />
            <h1 className="sr-only">Developer logs</h1>

            <div className="space-y-6 p-1">
                <Heading
                    variant="small"
                    title="Developer tools"
                    description="Tail of the local log file. Secrets are redacted before display."
                />

                <DevTabs />

                <section className="rounded-xl border border-border bg-card p-4">
                    <div className="mb-2 flex items-center justify-between">
                        <h2 className="font-mono text-xs text-muted-foreground">{path}</h2>
                    </div>

                    {available ? (
                        <pre className="max-h-[32rem] overflow-auto rounded-lg bg-muted p-3 text-xs leading-relaxed whitespace-pre-wrap">
                            {lines || '(log file is empty)'}
                        </pre>
                    ) : (
                        <p className="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No log file found. In production, logs are streamed to{' '}
                            <code>stderr</code> (visible in your hosting panel) — use the{' '}
                            <strong>Errors</strong> tab to review captured exceptions instead.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

DevLogs.layout = {
    breadcrumbs: [
        { title: 'Developer tools', href: '/admin/dev' },
        { title: 'Logs', href: '/admin/dev/logs' },
    ],
};
