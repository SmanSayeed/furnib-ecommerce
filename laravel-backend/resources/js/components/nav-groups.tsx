import { Link, usePage } from '@inertiajs/react';
import { ChevronRight  } from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { Auth } from '@/types';

export type AdminNavItem = {
    title: string;
    href?: string;
    icon: LucideIcon;
    permission?: string;
    soon?: boolean;
    children?: AdminNavItem[];
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

    const SoonButton = ({ item }: { item: AdminNavItem }) => (
        <SidebarMenuButton
            disabled
            tooltip={{ children: `${item.title} — coming soon` }}
            className="opacity-60"
        >
            <item.icon />
            <span>{item.title}</span>
            <Badge variant="secondary" className="ml-auto px-1.5 text-[10px] font-normal">
                Soon
            </Badge>
        </SidebarMenuButton>
    );

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
                            {items.map((item) => {
                                // Collapsible parent with sub-items.
                                if (item.children) {
                                    const children = item.children.filter((c) => can(c.permission));

                                    if (children.length === 0) {
                                        return null;
                                    }

                                    const open = children.some(
                                        (c) => c.href && isCurrentUrl(c.href),
                                    );

                                    return (
                                        <Collapsible
                                            key={item.title}
                                            asChild
                                            defaultOpen={open}
                                            className="group/collapsible"
                                        >
                                            <SidebarMenuItem>
                                                <CollapsibleTrigger asChild>
                                                    <SidebarMenuButton tooltip={{ children: item.title }}>
                                                        <item.icon />
                                                        <span>{item.title}</span>
                                                        <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                                    </SidebarMenuButton>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent>
                                                    <SidebarMenuSub>
                                                        {children.map((child) => (
                                                            <SidebarMenuSubItem key={child.title}>
                                                                {child.soon || !child.href ? (
                                                                    <SidebarMenuSubButton
                                                                        aria-disabled
                                                                        className="pointer-events-none opacity-60"
                                                                    >
                                                                        <span>{child.title}</span>
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="ml-auto px-1.5 text-[10px] font-normal"
                                                                        >
                                                                            Soon
                                                                        </Badge>
                                                                    </SidebarMenuSubButton>
                                                                ) : (
                                                                    <SidebarMenuSubButton
                                                                        asChild
                                                                        isActive={isCurrentUrl(child.href)}
                                                                    >
                                                                        <Link href={child.href} prefetch>
                                                                            <span>{child.title}</span>
                                                                        </Link>
                                                                    </SidebarMenuSubButton>
                                                                )}
                                                            </SidebarMenuSubItem>
                                                        ))}
                                                    </SidebarMenuSub>
                                                </CollapsibleContent>
                                            </SidebarMenuItem>
                                        </Collapsible>
                                    );
                                }

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        {item.soon || !item.href ? (
                                            <SoonButton item={item} />
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
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                );
            })}
        </>
    );
}
