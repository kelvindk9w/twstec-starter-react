import { Link, usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';
import type { AppLayoutProps } from '@/types';

/**
 * Painel: menu lateral (recolhível; gaveta no celular) + cabeçalho com a
 * trilha da página e o idioma. O avatar (menu da conta) fica no pé do menu.
 * Na carência do segundo fator obrigatório, um aviso com o prazo e o caminho
 * para a configuração aparece em todas as telas.
 */
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { twoFactorGrace } = usePage().props;
    const { t } = useTrans();
    const { route, has } = useRoute();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {twoFactorGrace && has('two-factor.setup') && (
                    <div className="px-4 pt-4 md:px-6">
                        <div
                            role="status"
                            className="rounded-md border border-amber-600/30 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                            data-two-factor-grace
                        >
                            {t('panel.profile.two_factor_grace', {
                                date: twoFactorGrace,
                            })}{' '}
                            <Link
                                href={route('two-factor.setup')}
                                className="font-medium underline"
                            >
                                {t('panel.profile.two_factor_grace_action')}
                            </Link>
                        </div>
                    </div>
                )}
                {children}
            </AppContent>
        </AppShell>
    );
}
