import { Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { Input } from '@/components/ui/input';

/**
 * Search field + arbitrary filter controls (selects, buttons) in one row.
 * Stacks vertically on mobile.
 */
export function FilterBar({
    search,
    onSearch,
    placeholder = 'Search…',
    children,
}: {
    search?: string;
    onSearch?: (value: string) => void;
    placeholder?: string;
    children?: ReactNode;
}) {
    return (
        <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center">
            <div className="relative flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={search}
                    onChange={(e) => onSearch?.(e.target.value)}
                    placeholder={placeholder}
                    className="pl-9"
                />
            </div>
            {children && <div className="flex items-center gap-2">{children}</div>}
        </div>
    );
}
