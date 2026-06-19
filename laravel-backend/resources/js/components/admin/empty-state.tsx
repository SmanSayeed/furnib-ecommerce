import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Centered empty/zero-data placeholder with an icon, message and optional CTA.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border bg-card p-12 text-center">
            <Icon className="size-8 text-muted-foreground/50" />
            <div>
                <p className="font-medium">{title}</p>
                {description && (
                    <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                )}
            </div>
            {action}
        </div>
    );
}
