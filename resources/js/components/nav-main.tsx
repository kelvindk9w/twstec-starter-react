import { Link } from '@inertiajs/react';
import { NavIcon } from '@/components/nav-icon';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavGroup } from '@/types';

/**
 * Um grupo do menu lateral ("Visão geral", "Conta"...). Os itens, os
 * rótulos e o item atual vêm do servidor — a mesma arquitetura de
 * informação do starter Livewire.
 */
export function NavMain({ group }: { group: NavGroup }) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
            <SidebarMenu>
                {group.items.map((item) => (
                    <SidebarMenuItem key={item.href}>
                        <SidebarMenuButton
                            asChild
                            isActive={item.active}
                            tooltip={{ children: item.label }}
                        >
                            <Link
                                href={item.href}
                                prefetch
                                aria-current={item.active ? 'page' : undefined}
                            >
                                <NavIcon name={item.icon} />
                                <span>{item.label}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
