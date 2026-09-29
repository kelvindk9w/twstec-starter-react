import { usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { TransactionPasswordForm } from '@/components/transaction-password-form';
import { useTrans } from '@/lib/i18n';

/**
 * Senha de transação (tela própria, a mesma do menu "Conta" do Livewire).
 * O envio é do pacote twstec/kit-auth.
 */
export default function TransactionPassword() {
    const { t } = useTrans();
    const { auth } = usePage().props;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={t('auth.ui.transaction_password_title')}
                description={t('auth.ui.transaction_password_subtitle')}
            />
            <SectionCard title={t('auth.ui.transaction_password_title')}>
                <TransactionPasswordForm
                    hasTransactionPassword={
                        auth.user?.hasTransactionPassword ?? false
                    }
                    submitLabel={t('auth.ui.save')}
                />
            </SectionCard>
        </div>
    );
}

TransactionPassword.layout = {
    breadcrumbs: [
        {
            title: 'panel.nav.transaction_password',
            route: 'transaction-password.edit',
        },
    ],
};
