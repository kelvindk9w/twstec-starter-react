import { usePage } from '@inertiajs/react';
import { Check, Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTrans } from '@/lib/i18n';

/**
 * Seletor de idioma. Cada idioma é um link comum (carga COMPLETA): a rota
 * locale.switch grava o cookie (e a conta, se há sessão) e devolve à mesma
 * página, que volta inteira no idioma novo — inclusive as traduções do front.
 */
export function LocaleSwitcher() {
    const { app } = usePage().props;
    const { t } = useTrans();
    const current = app.locales.find((locale) => locale.code === app.locale);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="gap-1.5"
                    aria-label={t('ui.locale.label')}
                    data-test="locale-switcher"
                >
                    <Languages className="size-4" />
                    <span className="text-xs font-medium uppercase">
                        {current?.code.slice(0, 2) ?? app.locale}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-48">
                {app.locales.map((locale) => (
                    <DropdownMenuItem key={locale.code} asChild>
                        <a
                            href={locale.url}
                            lang={locale.code.replace('_', '-')}
                            className="flex w-full cursor-pointer items-center"
                        >
                            {locale.label}
                            {locale.code === app.locale && (
                                <Check className="ml-auto size-4" />
                            )}
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
