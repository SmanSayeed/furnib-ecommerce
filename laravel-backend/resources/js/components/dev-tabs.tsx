import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, ScrollText, TerminalSquare } from 'lucide-react';
import type { ComponentType } from 'react';

const TABS: { href: string; label: string; icon: ComponentType<{ className?: string }> }[] = [
    { href: '/admin/dev', label: 'Console', icon: TerminalSquare },
    { href: '/admin/dev/errors', label: 'Errors', icon: AlertTriangle },
    { href: '/admin/dev/logs', label: 'Logs', icon: ScrollText },
];

export function DevTabs() {
    const { url } = usePage();
    // Strip query string so the active check is path-only.
    const path = url.split('?')[0];

    return (
        <nav className="flex flex-wrap gap-1 border-b border-border">
            {TABS.map((tab) => {
                const active =
                    tab.href === '/admin/dev' ? path === '/admin/dev' : path.startsWith(tab.href);

                return (
                    <Link
                        key={tab.href}
                        href={tab.href}
                        className={`-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition ${
                            active
                                ? 'border-accent text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        <tab.icon className="size-4" />
                        {tab.label}
                    </Link>
                );
            })}
        </nav>
    );
}
