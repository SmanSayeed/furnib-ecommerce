import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { branding, name } = usePage().props;
    const siteName = branding?.site_name || name || 'Furnib';

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary/10 ring-1 ring-sidebar-border">
                <img
                    src="/logo/furnib-favicon.png"
                    alt={siteName}
                    className="size-6 object-contain"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {siteName}
                </span>
            </div>
        </>
    );
}
