import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    History,
    LayoutGrid,
    MapPin,
    Package,
    Tags,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
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
import type { Can, NavItem } from '@/types';

// Plain paths: these pages are built from Day 4 on, so Wayfinder has no route helpers for them yet.
function navItemsFor(can: Can): NavItem[] {
    return [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ...(can.manage_assets
            ? [{ title: 'Assets', href: '/assets', icon: Package }]
            : []),
        {
            title: can.manage_assets ? 'Loans' : 'My Loans',
            href: '/loans',
            icon: ArrowLeftRight,
        },
        ...(can.manage_assets
            ? [
                  { title: 'Categories', href: '/categories', icon: Tags },
                  { title: 'Locations', href: '/locations', icon: MapPin },
              ]
            : []),
        ...(can.manage_users
            ? [{ title: 'Users', href: '/users', icon: Users }]
            : []),
        ...(can.view_audit
            ? [{ title: 'Audit Log', href: '/audit-logs', icon: History }]
            : []),
    ];
}

export function AppSidebar() {
    const { can } = usePage<{ can: Can }>().props;

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
                <NavMain items={navItemsFor(can)} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
