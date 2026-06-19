import type { ReactNode } from 'react';

/**
 * Standard page heading for admin screens: title + optional description on the
 * left, action buttons on the right. Stacks vertically on mobile.
 */
export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <h1 className="text-lg font-semibold tracking-tight">{title}</h1>
                {description && (
                    <p className="mt-0.5 text-sm text-muted-foreground">{description}</p>
                )}
            </div>
            {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
    );
}
