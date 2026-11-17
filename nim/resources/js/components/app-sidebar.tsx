import { Link, usePage } from '@inertiajs/react';
import {
    FileBarChart,
    FileSpreadsheet,
    History,
    Layers3,
    LayoutGrid,
    ScrollText,
    Settings2,
    ShieldCheck,
    UserCog,
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
import { index as membersIndex } from '@/routes/members';
import { create as createMembershipBatch } from '@/routes/membership-batches';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const mainNavItems: NavItem[] = [
        {
            title: 'Overview',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Members',
            href: membersIndex(),
            icon: Users,
        },
        ...(auth.can.membershipRegisterImport
            ? [
                  {
                      title: 'New members',
                      href: createMembershipBatch(),
                      icon: FileSpreadsheet,
                  },
              ]
            : []),
        ...(auth.can.membershipUpgradeImport
            ? [
                  {
                      title: 'Upgrades',
                      href: '/upgrade-batches/create',
                      icon: Layers3,
                  },
              ]
            : []),
        ...(auth.can.batchesView
            ? [
                  {
                      title: 'Import history',
                      href: '/batch-history',
                      icon: History,
                  },
              ]
            : []),
        ...(auth.can.reportsView
            ? [
                  {
                      title: 'Reports',
                      href: '/reports',
                      icon: FileBarChart,
                  },
              ]
            : []),
        ...(auth.can.settingsManage
            ? [
                  {
                      title: 'System settings',
                      href: '/settings/system',
                      icon: Settings2,
                  },
              ]
            : []),
        ...(auth.can.upgradeTypesManage
            ? [
                  {
                      title: 'Upgrade types',
                      href: '/settings/upgrade-types',
                      icon: ShieldCheck,
                  },
              ]
            : []),
        ...(auth.can.auditLogsView
            ? [
                  {
                      title: 'Audit logs',
                      href: '/audit-logs',
                      icon: ScrollText,
                  },
              ]
            : []),
        ...(auth.can.usersManage
            ? [
                  {
                      title: 'Users',
                      href: '/users',
                      icon: UserCog,
                  },
              ]
            : []),
    ];

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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
