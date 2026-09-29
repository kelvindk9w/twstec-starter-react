import { Form, usePage } from '@inertiajs/react';
import { StatusMessage } from '@/components/status-message';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

/**
 * Aviso de e-mail não confirmado: a única tela que a conta nova vê até
 * clicar no link. Diz para onde o e-mail foi, oferece o reenvio (com
 * intervalo no servidor) e a saída. O motivo de um reenvio recusado ou de um
 * link inválido fica FIXO na tela, ao lado do botão que resolve.
 */
export default function VerifyEmail({ email }: { email: string }) {
    const { t } = useTrans();
    const { route } = useRoute();
    const { flash } = usePage().props;

    return (
        <div className="space-y-6">
            <div className="space-y-2 text-center text-sm">
                <p className="text-muted-foreground" data-verification-intro>
                    {t('auth.email_verification.intro', { email })}
                </p>
                <p className="text-muted-foreground">
                    {t('auth.email_verification.hint')}
                </p>
            </div>

            <StatusMessage
                message={flash.verification_error}
                tone="warning"
                data-verification-error
            />

            <Form action={route('verification.send')} method="post">
                {({ processing }) => (
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                        data-test="resend-verification"
                    >
                        {processing && <Spinner />}
                        {t('auth.email_verification.resend')}
                    </Button>
                )}
            </Form>

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

VerifyEmail.layout = {
    title: 'auth.email_verification.title',
};
