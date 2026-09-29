import { Link, usePage } from '@inertiajs/react';
import { AccountSwitcher } from '@/components/account-switcher';
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
import { useRoute } from '@/lib/routes';

export function AppSidebar() {
    const { navigation } = usePage().props;
    const { route } = useRoute();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={route('dashboard')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {/* A conta atual em todo o painel (com o pacote de contas);
                    recolhido o menu, o seletor fica no cabeçalho. */}
                <div className="px-1 pt-1 group-data-[collapsible=icon]:hidden">
                    <AccountSwitcher />
                </div>
            </SidebarHeader>

            <SidebarContent className="gap-4 pt-2">
                {navigation.map((group) => (
                    <NavMain key={group.label} group={group} />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
