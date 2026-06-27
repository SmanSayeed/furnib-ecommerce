import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { DevTabs } from '@/components/dev-tabs';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Command = { id: string; label: string; group: string; destructive: boolean };
type RunResult = { id: string; label: string; exit_code: number; output: string } | null;

type System = Record<string, string | number | boolean>;
type Health = Record<string, boolean>;

const GROUPS: { key: string; title: string; hint: string }[] = [
    { key: 'cache', title: 'Cache', hint: 'Clear or rebuild caches. Safe & instant.' },
    { key: 'database', title: 'Database', hint: 'Migration status & run. Run is destructive — confirm first.' },
    { key: 'ops', title: 'Ops', hint: 'Storage link, queue restart, scheduler, system info.' },
];

function Badge({ ok }: { ok: boolean }) {
    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                ok ? 'bg-green-500/15 text-green-600' : 'bg-red-500/15 text-red-600'
            }`}
        >
            {ok ? '● OK' : '● Down'}
        </span>
    );
}

export default function DevConsole({
    commands,
    system,
    health,
    result,
}: {
    commands: Command[];
    system: System;
    health: Health;
    result: RunResult;
}) {
    const [busy, setBusy] = useState<string | null>(null);

    const run = (cmd: Command) => {
        if (cmd.destructive) {
            const sure = window.confirm(
                `"${cmd.label}" is a destructive command and will change the database. Continue?`,
            );

            if (!sure) {
return;
}
        }

        setBusy(cmd.id);
        router.post(
            '/admin/dev/run',
            { id: cmd.id, confirmed: cmd.destructive },
            { preserveScroll: true, onFinish: () => setBusy(null) },
        );
    };

    const grouped = (g: string) => commands.filter((c) => c.group === g);

    return (
        <>
            <Head title="Developer tools" />
            <h1 className="sr-only">Developer tools</h1>

            <div className="space-y-6 p-1">
                <Heading
                    variant="small"
                    title="Developer tools"
                    description="Owner-only. Run a fixed set of safe maintenance commands, check system health, and view the last command output."
                />

                <DevTabs />

                {/* System info + health */}
                <section className="rounded-xl border border-border bg-card p-4">
                    <div className="mb-3 flex items-center gap-3">
                        <h2 className="text-sm font-medium text-muted-foreground">System</h2>
                        <span className="flex items-center gap-2 text-xs">
                            DB <Badge ok={!!health.database} /> Cache <Badge ok={!!health.cache} />
                        </span>
                    </div>
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3 lg:grid-cols-4">
                        {Object.entries(system).map(([k, v]) => (
                            <div key={k} className="min-w-0">
                                <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                                    {k}
                                </dt>
                                <dd className="truncate font-medium">{String(v)}</dd>
                            </div>
                        ))}
                    </dl>
                </section>

                {/* Command groups */}
                {GROUPS.map((g) => (
                    <section key={g.key} className="rounded-xl border border-border bg-card p-4">
                        <h2 className="text-sm font-medium text-muted-foreground">{g.title}</h2>
                        <p className="mt-1 text-xs text-muted-foreground">{g.hint}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {grouped(g.key).map((cmd) => (
                                <Button
                                    key={cmd.id}
                                    variant={cmd.destructive ? 'destructive' : 'outline'}
                                    size="sm"
                                    disabled={busy !== null}
                                    onClick={() => run(cmd)}
                                >
                                    {busy === cmd.id ? 'Running…' : cmd.label}
                                </Button>
                            ))}
                        </div>
                    </section>
                ))}

                {/* Last command output */}
                {result && (
                    <section className="rounded-xl border border-border bg-card p-4">
                        <div className="mb-2 flex items-center justify-between">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                Output — {result.label}
                            </h2>
                            <span
                                className={`text-xs font-medium ${
                                    result.exit_code === 0 ? 'text-green-600' : 'text-amber-600'
                                }`}
                            >
                                exit {result.exit_code}
                            </span>
                        </div>
                        <pre className="max-h-80 overflow-auto rounded-lg bg-muted p-3 text-xs leading-relaxed whitespace-pre-wrap">
                            {result.output || '(no output)'}
                        </pre>
                    </section>
                )}
            </div>
        </>
    );
}

DevConsole.layout = {
    breadcrumbs: [{ title: 'Developer tools', href: '/admin/dev' }],
};
