import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { DevTabs } from '@/components/dev-tabs';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type ErrorRow = {
    id: number;
    level: string;
    message: string;
    exception: string | null;
    location: string | null;
    method: string | null;
    url: string | null;
    at: string | null;
};

export default function DevErrors({ errors }: { errors: ErrorRow[] }) {
    const clear = () => {
        if (!window.confirm('Delete all captured errors? This cannot be undone.')) {
            return;
        }

        router.delete('/admin/dev/errors', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Developer — errors" />
            <h1 className="sr-only">Developer errors</h1>

            <div className="space-y-6 p-1">
                <Heading
                    variant="small"
                    title="Developer tools"
                    description="Captured application exceptions. Works in production too, where logs go to stderr. Secrets are redacted at capture time."
                />

                <DevTabs />

                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        {errors.length === 0
                            ? 'No errors captured.'
                            : `Showing the latest ${errors.length} error${errors.length === 1 ? '' : 's'}.`}
                    </p>
                    {errors.length > 0 && (
                        <Button variant="destructive" size="sm" onClick={clear}>
                            <Trash2 className="size-4" /> Clear all
                        </Button>
                    )}
                </div>

                {errors.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground">
                        Nothing here — the application has not logged any errors yet.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {errors.map((e) => (
                            <article
                                key={e.id}
                                className="rounded-xl border border-border bg-card p-4"
                            >
                                <div className="flex flex-wrap items-center gap-2 text-xs">
                                    <span className="rounded-md bg-red-500/15 px-2 py-0.5 font-medium text-red-600">
                                        {e.level}
                                    </span>
                                    {e.exception && (
                                        <span className="font-mono text-muted-foreground">
                                            {e.exception}
                                        </span>
                                    )}
                                    <span className="ml-auto text-muted-foreground">{e.at}</span>
                                </div>
                                <p className="mt-2 text-sm font-medium break-words">{e.message}</p>
                                <dl className="mt-2 grid gap-1 text-xs text-muted-foreground">
                                    {e.location && (
                                        <div className="break-all font-mono">{e.location}</div>
                                    )}
                                    {e.url && (
                                        <div className="break-all">
                                            {e.method} {e.url}
                                        </div>
                                    )}
                                </dl>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

DevErrors.layout = {
    breadcrumbs: [
        { title: 'Developer tools', href: '/admin/dev' },
        { title: 'Errors', href: '/admin/dev/errors' },
    ],
};
