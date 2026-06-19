import type { LucideIcon } from 'lucide-react';

/**
 * KPI tile for dashboards: label + big value, optional icon and sub-hint.
 */
export function StatCard({
    label,
    value,
    icon: Icon,
    hint,
}: {
    label: string;
    value: string | number;
    icon?: LucideIcon;
    hint?: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">{label}</span>
                {Icon && <Icon className="size-4 text-muted-foreground" />}
            </div>
            <div className="mt-2 text-2xl font-semibold tracking-tight">{value}</div>
            {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}
