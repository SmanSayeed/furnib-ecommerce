import { Link } from '@inertiajs/react';
import {
    Boxes,
    CreditCard,
    Database,
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
    TerminalSquare,
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
            { title: 'Inquiries', icon: MessageCircle, soon: true },
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
        items: [{ title: 'Transactions', icon: CreditCard, soon: true }],
    },
    {
        label: 'Shipping',
        permission: 'orders.view',
        items: [
            { title: 'Shipping zones', href: '/admin/shipping/zones', icon: Truck },
            { title: 'Consignments', icon: Truck, soon: true },
        ],
    },
    {
        label: 'Marketing',
        permission: 'marketing.manage',
        items: [
            { title: 'Coupons', icon: TicketPercent, soon: true },
            { title: 'Tracking & Pixels', href: '/settings/marketing', icon: Megaphone },
        ],
    },
    {
        label: 'Settings',
        permission: 'settings.manage',
        items: [
            { title: 'Site & branding', href: '/settings/site', icon: Store },
            { title: 'Storage (R2)', href: '/settings/storage', icon: Database },
            { title: 'Staff & roles', icon: UsersRound, soon: true },
            { title: 'Integrations', href: '/settings/integrations', icon: CreditCard },
        ],
    },
    {
        label: 'System',
        items: [
            { title: 'Audit log', icon: ScrollText, permission: 'audit.view', soon: true },
            { title: 'Maintenance', icon: ShieldAlert, permission: 'maintenance.manage', soon: true },
        ],
    },
    {
        label: 'Developer',
        permission: 'developer.access',
        items: [{ title: 'Developer tools', href: '/admin/dev', icon: TerminalSquare }],
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
