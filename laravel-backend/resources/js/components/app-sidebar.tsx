import { Link } from '@inertiajs/react';
import {
    Boxes,
    CreditCard,
    FileText,
    FolderTree,
    LayoutGrid,
    Megaphone,
    MessageCircle,
    Package,
    ScrollText,
    ShieldAlert,
    ShoppingCart,
    Store,
    TicketPercent,
    Truck,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import {  NavGroups } from '@/components/nav-groups';
import type {AdminNavGroup} from '@/components/nav-groups';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';

const navGroups: AdminNavGroup[] = [
    {
        label: 'Overview',
        items: [{ title: 'Dashboard', href: dashboard().url, icon: LayoutGrid }],
    },
    {
        label: 'Catalog',
        permission: 'catalog.view',
        items: [
            { title: 'Products', icon: Package, soon: true },
            { title: 'Categories', href: '/admin/catalog/categories', icon: FolderTree },
            { title: 'Inventory', icon: Boxes, soon: true },
        ],
    },
    {
        label: 'Sales',
        permission: 'orders.view',
        items: [
            { title: 'Orders', icon: ShoppingCart, soon: true },
            { title: 'Invoices', icon: FileText, soon: true },
            { title: 'Inquiries', icon: MessageCircle, soon: true },
        ],
    },
    {
        label: 'Customers',
        permission: 'users.manage',
        items: [{ title: 'Customers', icon: Users, soon: true }],
    },
    {
        label: 'Payments',
        permission: 'payments.view',
        items: [{ title: 'Transactions', icon: CreditCard, soon: true }],
    },
    {
        label: 'Shipping',
        permission: 'orders.manage',
        items: [{ title: 'Consignments', icon: Truck, soon: true }],
    },
    {
        label: 'Marketing',
        permission: 'marketing.manage',
        items: [
            { title: 'Coupons', icon: TicketPercent, soon: true },
            { title: 'Pixels & SEO', icon: Megaphone, soon: true },
        ],
    },
    {
        label: 'Settings',
        permission: 'settings.manage',
        items: [
            { title: 'Site & branding', href: '/settings/site', icon: Store },
            { title: 'Staff & roles', icon: UsersRound, soon: true },
            { title: 'Integrations', icon: CreditCard, soon: true },
        ],
    },
    {
        label: 'System',
        items: [
            { title: 'Audit log', icon: ScrollText, permission: 'audit.view', soon: true },
            { title: 'Maintenance', icon: ShieldAlert, permission: 'maintenance.manage', soon: true },
        ],
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavGroups groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
