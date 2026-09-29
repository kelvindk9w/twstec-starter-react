import { Check, Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTheme } from '@/hooks/use-theme';
import { useTrans } from '@/lib/i18n';
import type { Theme } from '@/types';

const options: { value: Theme; icon: typeof Sun }[] = [
    { value: 'system', icon: Monitor },
    { value: 'light', icon: Sun },
    { value: 'dark', icon: Moon },
];

/**
 * Tema (sistema, claro, escuro) fora do menu da conta — nas telas de
 * autenticação e na página inicial.
 */
export function ThemeToggle() {
    const { theme, setTheme } = useTheme();
    const { t } = useTrans();
    const Current =
        options.find((option) => option.value === theme)?.icon ?? Monitor;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('ui.theme.toggle')}
                    data-test="theme-toggle"
                >
                    <Current className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {options.map(({ value, icon: Icon }) => (
                    <DropdownMenuItem
                        key={value}
                        onSelect={() => setTheme(value)}
                        role="menuitemradio"
                        aria-checked={theme === value}
                    >
                        <Icon className="mr-2 size-4" />
                        {t(`ui.theme.${value}`)}
                        {theme === value && (
                            <Check className="ml-auto size-4" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
