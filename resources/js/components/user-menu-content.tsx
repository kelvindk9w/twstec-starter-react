import { Link, router } from '@inertiajs/react';
import {
    Check,
    CircleUser,
    ExternalLink,
    LayoutGrid,
    LogOut,
    Monitor,
    Moon,
    Sun,
} from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useTheme } from '@/hooks/use-theme';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';
import type { Theme, User } from '@/types';

const themes: { value: Theme; icon: typeof Sun }[] = [
    { value: 'system', icon: Monitor },
    { value: 'light', icon: Sun },
    { value: 'dark', icon: Moon },
];

/**
 * O menu da conta (o avatar) — os mesmos itens do starter Livewire: quem
 * está logado, painel, perfil, o tema (os três estados, com ✓ no atual), a
 * volta ao site e a saída.
 */
export function UserMenuContent({ user }: { user: User }) {
    const cleanup = useMobileNavigation();
    const { t } = useTrans();
    const { route } = useRoute();
    const { theme, setTheme } = useTheme();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={route('dashboard')}
                        onClick={cleanup}
                    >
                        <LayoutGrid className="mr-2" />
                        {t('panel.nav.dashboard')}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={route('panel.profile')}
                        prefetch
                        onClick={cleanup}
                    >
                        <CircleUser className="mr-2" />
                        {t('panel.nav.profile')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuLabel className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                {t('ui.theme.label')}
            </DropdownMenuLabel>
            <DropdownMenuGroup>
                {themes.map(({ value, icon: Icon }) => (
                    <DropdownMenuItem
                        key={value}
                        onSelect={(event) => {
                            event.preventDefault();
                            setTheme(value);
                        }}
                        aria-checked={theme === value}
                        role="menuitemradio"
                    >
                        <Icon className="mr-2" />
                        {t(`ui.theme.${value}`)}
                        {theme === value && <Check className="ml-auto" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <a className="block w-full cursor-pointer" href={route('home')}>
                    <ExternalLink className="mr-2" />
                    {t('ui.nav.back_to_site')}
                </a>
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={route('logout')}
                    method="post"
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {t('auth.ui.logout')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
