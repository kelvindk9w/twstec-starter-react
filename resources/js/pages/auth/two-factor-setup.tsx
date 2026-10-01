import { Form, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { StatusMessage } from '@/components/status-message';
import { TransactionPasswordForm } from '@/components/transaction-password-form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

type Props = {
    email: string;
    hasTransactionPassword: boolean;
    codeSent: boolean;
    codeTtlMinutes: number;
    graceEndsAt: string | null;
};

/**
 * Configuração do segundo fator OBRIGATÓRIO (AUTH_TWO_FACTOR_REQUIRED).
 *
 * Quem chega aqui está logado, mas a instalação exige a verificação em duas
 * etapas e a conta ainda não a ligou: o resto do painel fica fechado até
 * terminar (EnsureTwoFactorIsConfigured, do pacote). É a MESMA regra de ligar
 * pelo perfil — senha de transação → código por e-mail → liga —, com os
 * envios do pacote. A página mostra o passo em que a pessoa está:
 *   1. sem senha de transação: defini-la (o envio de sempre);
 *   2. com ela: confirmar com a senha → código por e-mail;
 *   3. código enviado: digitar o código (ou pedir outro).
 * Na carência, diz até quando dá para adiar e oferece voltar ao painel.
 */
export default function TwoFactorSetup({
    email,
    hasTransactionPassword,
    codeSent,
    codeTtlMinutes,
    graceEndsAt,
}: Props) {
    const { t } = useTrans();
    const { route } = useRoute();
    const { errors } = usePage().props;

    return (
        <div className="space-y-6">
            <p
                className="text-center text-sm text-muted-foreground"
                data-two-factor-setup-intro
            >
                {t('auth.two_factor_setup.intro', { email })}
            </p>

            {graceEndsAt && (
                <StatusMessage
                    message={t('auth.two_factor_setup.grace', {
                        date: graceEndsAt,
                    })}
                    tone="warning"
                    data-two-factor-setup-grace
                />
            )}

            <StatusMessage
                message={errors.two_factor}
                tone="warning"
                data-two-factor-setup-error
            />

            {!hasTransactionPassword ? (
                <div
                    className="space-y-3"
                    data-two-factor-setup-transaction-password
                >
                    <div className="space-y-1">
                        <h2 className="text-sm font-semibold">
                            {t(
                                'auth.two_factor_setup.transaction_password_heading',
                            )}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'auth.two_factor_setup.transaction_password_hint',
                            )}
                        </p>
                    </div>
                    <TransactionPasswordForm
                        hasTransactionPassword={false}
                        submitLabel={t('auth.ui.save')}
                    />
                </div>
            ) : (
                <div className="space-y-4">
                    {codeSent ? (
                        <Form
                            action={route('two-factor.setup.store')}
                            method="post"
                            resetOnError
                            className="grid gap-4"
                            data-two-factor-setup-code
                        >
                            {({ processing, errors: formErrors }) => (
                                <>
                                    <p
                                        className="text-sm text-muted-foreground"
                                        data-two-factor-setup-code-intro
                                    >
                                        {t('auth.two_factor_setup.code_intro', {
                                            email,
                                            minutes: codeTtlMinutes,
                                        })}
                                    </p>
                                    <div className="grid gap-2">
                                        <Label htmlFor="code">
                                            {t('auth.two_factor.code_label')}
                                        </Label>
                                        <Input
                                            id="code"
                                            name="code"
                                            inputMode="numeric"
                                            pattern="[0-9]*"
                                            maxLength={6}
                                            autoComplete="one-time-code"
                                            required
                                            autoFocus
                                            aria-invalid={
                                                formErrors.code
                                                    ? true
                                                    : undefined
                                            }
                                        />
                                        <InputError message={formErrors.code} />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('auth.two_factor_setup.submit')}
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        {t('auth.two_factor_setup.resend_hint')}
                                    </p>
                                </>
                            )}
                        </Form>
                    ) : (
                        <div className="space-y-1">
                            <h2 className="text-sm font-semibold">
                                {t('auth.two_factor_setup.confirm_heading')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('auth.two_factor_setup.confirm_hint')}
                            </p>
                        </div>
                    )}

                    <Form
                        action={route('two-factor.setup.code')}
                        method="post"
                        resetOnSuccess
                        resetOnError
                        className="grid gap-4"
                        data-two-factor-setup-send
                    >
                        {({ processing, errors: formErrors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="setup_transaction_password">
                                        {t(
                                            'auth.ui.transaction_password_title',
                                        )}
                                    </Label>
                                    <PasswordInput
                                        id="setup_transaction_password"
                                        name="transaction_password"
                                        autoComplete="off"
                                        required
                                        aria-invalid={
                                            formErrors.transaction_password
                                                ? true
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        message={
                                            formErrors.transaction_password
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant={codeSent ? 'secondary' : 'default'}
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {codeSent
                                        ? t('auth.two_factor.resend')
                                        : t('auth.two_factor_setup.send_code')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            )}

            <p className="text-center text-xs text-muted-foreground">
                {t('panel.profile.two_factor_recovery')}
            </p>

            {graceEndsAt && (
                <Button
                    asChild
                    variant="ghost"
                    className="w-full"
                    data-two-factor-setup-later
                >
                    <Link href={route('dashboard')}>
                        {t('auth.two_factor_setup.later')}
                    </Link>
                </Button>
            )}

            <Form action={route('logout')} method="post">
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="ghost"
                        className="w-full"
                        disabled={processing}
                    >
                        {t('auth.ui.logout')}
                    </Button>
                )}
            </Form>
        </div>
    );
}

TwoFactorSetup.layout = {
    title: 'auth.two_factor_setup.title',
};
