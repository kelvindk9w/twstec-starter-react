import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

/**
 * Login. O envio vai para o controller do pacote twstec/kit-auth (bloqueio
 * por tentativas, conta ativa, anti-enumeração, sessão regenerada, segundo
 * fator); a mensagem de erro chega pronta do servidor no campo `email`.
 */
export default function Login() {
    const { t } = useTrans();
    const { route } = useRoute();

    return (
        <Form
            action={route('login')}
            method="post"
            resetOnSuccess={['password']}
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('auth.ui.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="username"
                                aria-invalid={errors.email ? true : undefined}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">
                                    {t('auth.ui.password')}
                                </Label>
                                <TextLink
                                    href={route('password.request')}
                                    className="ml-auto text-sm"
                                    tabIndex={5}
                                >
                                    {t('auth.ui.forgot_password')}
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                aria-invalid={
                                    errors.password ? true : undefined
                                }
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                value="1"
                                tabIndex={3}
                            />
                            <Label htmlFor="remember">
                                {t('auth.ui.remember_me')}
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 w-full"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            {t('auth.ui.login_submit')}
                        </Button>
                    </div>

                    <div className="text-center text-sm text-muted-foreground">
                        <TextLink href={route('register')} tabIndex={6}>
                            {t('auth.ui.register_link')}
                        </TextLink>
                    </div>
                </>
            )}
        </Form>
    );
}

Login.layout = {
    title: 'auth.ui.login_title',
};
