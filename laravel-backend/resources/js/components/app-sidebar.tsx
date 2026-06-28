import { Link } from '@inertiajs/react';
import {
    Boxes,
    CreditCard,
    Database,
    FileText,
    FolderTree,
    LayoutGrid,
    Mail,
    Megaphone,
    Package,
    ScrollText,
    ShieldAlert,
    ShoppingCart,
    Store,
    TerminalSquare,
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
            { title: 'Products', href: '/admin/catalog/products', icon: Package },
            { title: 'Categories', href: '/admin/catalog/categories', icon: FolderTree },
            { title: 'Inventory', icon: Boxes, soon: true },
        ],
    },
    {
        label: 'Sales',
        permission: 'orders.view',
        items: [
            { title: 'Orders', href: '/admin/orders', icon: ShoppingCart },
            { title: 'Invoices', href: '/admin/invoices', icon: FileText },
        ],
    },
    {
        label: 'Customers',
        permission: 'orders.view',
        items: [{ title: 'Customers', href: '/admin/customers', icon: Users }],
    },
    {
        label: 'Payments',
        permission: 'payments.view',
        items: [{ title: 'Transactions', href: '/admin/payments', icon: CreditCard }],
    },
    {
        label: 'Shipping',
        permission: 'orders.view',
        items: [
            { title: 'Shipping charge', href: '/admin/shipping/zones', icon: Truck },
            { title: 'Consignments', href: '/admin/shipping/consignments', icon: Truck },
        ],
    },
    {
        label: 'Marketing',
        permission: 'marketing.manage',
        items: [
            { title: 'Tracking & Pixels', href: '/settings/marketing', icon: Megaphone },
        ],
    },
    {
        label: 'Settings',
        permission: 'settings.manage',
        items: [
            { title: 'Site & branding', href: '/settings/site', icon: Store },
            { title: 'Footer pages', href: '/admin/pages', icon: FileText },
            { title: 'Subscriptions', href: '/admin/subscribers', icon: Mail },
            { title: 'Storage (R2)', href: '/settings/storage', icon: Database },
            { title: 'Staff & roles', icon: UsersRound, soon: true },
            { title: 'Integrations', href: '/settings/integrations', icon: CreditCard },
        ],
    },
    {
        label: 'System',
        items: [
            { title: 'Developer tools', href: '/admin/dev', icon: TerminalSquare, permission: 'developer.access' },
            { title: 'Audit log', href: '/admin/audit-logs', icon: ScrollText, permission: 'audit.view' },
            { title: 'Maintenance', href: '/admin/maintenance', icon: ShieldAlert, permission: 'maintenance.manage' },
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
