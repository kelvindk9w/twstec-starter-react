import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { StatusMessage } from '@/components/status-message';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';
import { usePage } from '@inertiajs/react';

/**
 * Pedido do link de redefinição. Anti-enumeração: a resposta é a MESMA
 * exista ou não o e-mail (o aviso fica fixo na tela).
 */
export default function ForgotPassword() {
    const { t } = useTrans();
    const { route } = useRoute();
    const { flash } = usePage().props;

    return (
        <div className="space-y-6">
            <StatusMessage message={flash.status} />

            <Form action={route('password.email')} method="post">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('auth.ui.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                required
                                autoFocus
                                aria-invalid={errors.email ? true : undefined}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="my-6 flex items-center justify-start">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.ui.forgot_submit')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            <div className="text-center text-sm text-muted-foreground">
                <TextLink href={route('login')}>
                    {t('auth.ui.login_link')}
                </TextLink>
            </div>
        </div>
    );
}

ForgotPassword.layout = {
    title: 'auth.ui.forgot_title',
    description: 'auth.ui.forgot_subtitle',
};
