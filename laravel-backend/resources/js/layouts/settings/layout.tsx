import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { Auth, NavItem } from '@/types';

type SettingsNavItem = NavItem & { permission?: string };

type SettingsNavGroup = { label?: string; items: SettingsNavItem[] };

const navGroups: SettingsNavGroup[] = [
    {
        items: [
            { title: 'Profile', href: edit(), icon: null },
            { title: 'Security', href: editSecurity(), icon: null },
            { title: 'Appearance', href: editAppearance(), icon: null },
        ],
    },
    {
        label: 'Store',
        items: [
            { title: 'Site & branding', href: '/settings/site', icon: null, permission: 'settings.manage' },
            { title: 'Marketing & tracking', href: '/settings/marketing', icon: null, permission: 'marketing.manage' },
            { title: 'Storage (R2)', href: '/settings/storage', icon: null, permission: 'settings.manage' },
            { title: 'Integrations', href: '/settings/integrations', icon: null, permission: 'settings.manage' },
        ],
    },
    {
        label: 'Footer',
        items: [
            { title: 'Footer pages', href: '/admin/pages', icon: null, permission: 'settings.manage' },
            { title: 'Footer social icons', href: '/settings/footer/social', icon: null, permission: 'settings.manage' },
            { title: 'Footer details', href: '/settings/footer/details', icon: null, permission: 'settings.manage' },
            { title: 'Subscriptions', href: '/admin/subscribers', icon: null, permission: 'settings.manage' },
        ],
    },
    {
        label: 'Access',
        items: [{ title: 'Staff & roles', href: '/admin/staff', icon: null, permission: 'users.manage' }],
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage<{ auth: Auth }>().props;
    const can = (permission?: string) =>
        !permission || auth.permissions.includes(permission);

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-52">
                    <nav className="flex flex-col space-y-4" aria-label="Settings">
                        {navGroups.map((group, gi) => {
                            const items = group.items.filter((item) => can(item.permission));

                            if (items.length === 0) {
                                return null;
                            }

                            return (
                                <div key={group.label ?? `group-${gi}`} className="flex flex-col space-y-1">
                                    {group.label && (
                                        <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground/70">
                                            {group.label}
                                        </p>
                                    )}
                                    {items.map((item, index) => (
                                        <Button
                                            key={`${toUrl(item.href)}-${index}`}
                                            size="sm"
                                            variant="ghost"
                                            asChild
                                            className={cn('w-full justify-start', {
                                                'bg-muted': isCurrentOrParentUrl(item.href),
                                            })}
                                        >
                                            <Link href={item.href}>{item.title}</Link>
                                        </Button>
                                    ))}
                                </div>
                            );
                        })}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
