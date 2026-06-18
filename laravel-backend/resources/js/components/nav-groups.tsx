import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { Auth } from '@/types';

export type AdminNavItem = {
    title: string;
    href?: string;
    icon: LucideIcon;
    permission?: string;
    soon?: boolean;
};

export type AdminNavGroup = {
    label: string;
    permission?: string;
    items: AdminNavItem[];
};

export function NavGroups({ groups }: { groups: AdminNavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { auth } = usePage<{ auth: Auth }>().props;
    const can = (permission?: string) =>
        !permission || auth.permissions.includes(permission);

    return (
        <>
            {groups.map((group) => {
                const items = group.items.filter((item) => can(item.permission));

                if (!can(group.permission) || items.length === 0) {
                    return null;
                }

                return (
                    <SidebarGroup key={group.label} className="px-2 py-0">
                        <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    {item.soon || !item.href ? (
                                        <SidebarMenuButton
                                            disabled
                                            tooltip={{ children: `${item.title} — coming soon` }}
                                            className="opacity-60"
                                        >
                                            <item.icon />
                                            <span>{item.title}</span>
                                            <Badge
                                                variant="secondary"
                                                className="ml-auto px-1.5 text-[10px] font-normal"
                                            >
                                                Soon
                                            </Badge>
                                        </SidebarMenuButton>
                                    ) : (
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isCurrentUrl(item.href)}
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link href={item.href} prefetch>
                                                <item.icon />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    )}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                );
            })}
        </>
    );
}
