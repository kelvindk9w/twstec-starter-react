import { Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    Check,
    ChevronsUpDown,
    CircleUser,
    Plus,
    Users,
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';
import { cn } from '@/lib/utils';

/**
 * SELETOR DE CONTA — em todo o painel (o <x-account-switcher> do starter
 * Livewire): a conta atual e o papel da pessoa nela; abre a lista das contas
 * dela, cada uma com o papel (✓ na atual), e os atalhos "Conta e membros" e
 * "Criar conta de empresa".
 *
 * Trocar é um POST para a rota do pacote de contas (accounts.switch), que só
 * aceita conta de que a pessoa é membro; a tela volta para a MESMA página,
 * agora com os dados da conta escolhida. Os dados vêm da prop compartilhada
 * `accountMenu` (sem o pacote, ela é null e o seletor não aparece).
 */
export function AccountSwitcher({
    className,
    compact = false,
}: {
    className?: string;
    compact?: boolean;
}) {
    const { accountMenu } = usePage().props;
    const { t } = useTrans();
    const { route, has } = useRoute();

    if (!accountMenu || !has('accounts.switch')) {
        return null;
    }

    const { current, accounts } = accountMenu;
    const Icon = current.personal ? CircleUser : Building2;

    const switchTo = (uuid: string) =>
        router.post(
            route('accounts.switch', { account: uuid }),
            {},
            { preserveScroll: true },
        );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={t('ui.account_switcher.label', {
                        account: current.name,
                    })}
                    className={cn(
                        'flex min-h-11 w-full items-center gap-3 rounded-lg border bg-background px-3 py-2 text-left transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        className,
                    )}
                    data-account-switcher
                >
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                        <Icon className="size-4" />
                    </span>
                    <span className="min-w-0 flex-1">
                        <span
                            className="block truncate text-sm font-medium"
                            data-current-account
                        >
                            {current.name}
                        </span>
                        {!compact && (
                            <span className="block truncate text-xs text-muted-foreground">
                                {current.roleLabel}
                            </span>
                        )}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 text-muted-foreground" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="w-72 max-w-[calc(100vw-2rem)]"
                align="start"
            >
                <DropdownMenuLabel className="text-xs tracking-widest text-muted-foreground uppercase">
                    {t('ui.account_switcher.heading')}
                </DropdownMenuLabel>
                <div className="max-h-72 overflow-y-auto">
                    {accounts.map((account) => {
                        const ItemIcon = account.personal
                            ? CircleUser
                            : Building2;

                        return (
                            <DropdownMenuItem
                                key={account.uuid}
                                onSelect={() =>
                                    account.current
                                        ? undefined
                                        : switchTo(account.uuid)
                                }
                                aria-current={
                                    account.current ? 'true' : undefined
                                }
                                data-account-option={account.uuid}
                                className="gap-3"
                            >
                                <ItemIcon className="size-4 text-muted-foreground" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate">
                                        {account.name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {account.roleLabel}
                                    </span>
                                </span>
                                {account.current && (
                                    <Check className="size-4 shrink-0 text-primary" />
                                )}
                            </DropdownMenuItem>
                        );
                    })}
                </div>
                <DropdownMenuSeparator />
                {has('panel.account') && (
                    <DropdownMenuItem asChild>
                        <Link href={route('panel.account')} className="gap-3">
                            <Users className="size-4 text-muted-foreground" />
                            {t('ui.account_switcher.manage')}
                        </Link>
                    </DropdownMenuItem>
                )}
                {has('panel.accounts.create') && (
                    <DropdownMenuItem asChild>
                        <Link
                            href={route('panel.accounts.create')}
                            className="gap-3"
                        >
                            <Plus className="size-4 text-muted-foreground" />
                            {t('ui.account_switcher.create')}
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
