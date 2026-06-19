import { AppearanceToggle } from '@/components/appearance-toggle';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/60 bg-background/80 px-4 backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6">
            <div className="flex flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1 size-9" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <AppearanceToggle />
        </header>
    );
}
