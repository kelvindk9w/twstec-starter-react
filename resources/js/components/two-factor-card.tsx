import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SectionCard } from '@/components/section-card';
import { SensitiveActionDialog } from '@/components/sensitive-action-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

type TwoFactor = {
    available: boolean;
    enabled: boolean;
    blockedReason: string | null;
};

/**
 * Verificação em duas etapas do login: o estado e o botão. Ligar e desligar
 * são AÇÕES SENSÍVEIS, com a confirmação do painel (SensitiveActionDialog):
 * senha de transação → código por e-mail → a operação. O token de ação
 * sensível nasce e morre no servidor (TwoFactorPreferenceController); o
 * navegador só manda a senha e o código.
 */
export function TwoFactorCard({ twoFactor }: { twoFactor: TwoFactor }) {
    const { t } = useTrans();
    const { route } = useRoute();
    const { auth, errors } = usePage().props;

    const [open, setOpen] = useState(false);

    if (!twoFactor.available) {
        return null;
    }

    return (
        <SectionCard
            id="two-factor"
            title={t('panel.profile.two_factor_heading')}
            description={t('panel.profile.two_factor_hint', {
                email: auth.user?.email ?? '',
            })}
        >
            <div className="space-y-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Badge
                        variant={twoFactor.enabled ? 'default' : 'secondary'}
                        data-two-factor-state
                    >
                        {twoFactor.enabled
                            ? t('panel.profile.two_factor_on')
                            : t('panel.profile.two_factor_off')}
                    </Badge>
                    <Button
                        type="button"
                        variant={twoFactor.enabled ? 'outline' : 'default'}
                        disabled={twoFactor.blockedReason !== null}
                        onClick={() => setOpen(true)}
                        data-test="two-factor-toggle"
                    >
                        {twoFactor.enabled
                            ? t('panel.profile.two_factor_disable')
                            : t('panel.profile.two_factor_enable')}
                    </Button>
                </div>
                {twoFactor.blockedReason && (
                    <p
                        className="text-sm text-muted-foreground"
                        data-two-factor-blocked
                    >
                        {twoFactor.blockedReason}
                    </p>
                )}
                <InputError message={errors.two_factor} />
                <p className="text-xs text-muted-foreground">
                    {t('panel.profile.two_factor_recovery')}
                </p>
            </div>

            <SensitiveActionDialog
                open={open}
                onClose={() => setOpen(false)}
                description={
                    twoFactor.enabled
                        ? t('panel.profile.two_factor_confirm_disable')
                        : t('panel.profile.two_factor_confirm_enable')
                }
                codeUrl={route('panel.two-factor.code')}
                confirmUrl={route('panel.two-factor.update')}
                confirmMethod="put"
                data={{ enabled: !twoFactor.enabled }}
                onConfirmed={() => setOpen(false)}
            />
        </SectionCard>
    );
}
