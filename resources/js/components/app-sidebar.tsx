import { NavFooter } from '@/components/nav-footer';
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
import { useTranslations } from '@/hooks/useTranslations';
import { index as serversIndex } from '@/routes/servers';
import { index as sitesIndex } from '@/routes/sites';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Globe, Server } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const t = useTranslations();

    const mainNavItems: NavItem[] = [
        {
            title: t('servers'),
            href: serversIndex(),
            icon: Server,
        },
        {
            title: t('sites'),
            href: sitesIndex(),
            icon: Globe,
        },
    ];

    const footerNavItems: NavItem[] = [
        // {
        //     title: t('repository'),
        //     href: 'https://github.com/laravel/react-starter-kit',
        //     icon: Folder,
        // },
        // {
        //     title: t('documentation'),
        //     href: 'https://laravel.com/docs/starter-kits#react',
        //     icon: BookOpen,
        // },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={serversIndex()} prefetch>
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
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
