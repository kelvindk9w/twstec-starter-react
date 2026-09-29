import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

/**
 * Cadastro. A política de senha (e a dica embaixo do campo) é a do pacote
 * (PasswordPolicy — config/auth.php → password_rules). Com a verificação de
 * e-mail ligada, a conta nova vai para a tela de aviso, não para o painel.
 */
export default function Register({ passwordHint }: { passwordHint: string }) {
    const { t } = useTrans();
    const { route } = useRoute();

    return (
        <Form
            action={route('register')}
            method="post"
            resetOnSuccess={['password', 'password_confirmation']}
            disableWhileProcessing
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('auth.ui.name')}</Label>
                            <Input
                                id="name"
                                type="text"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="name"
                                name="name"
                                aria-invalid={errors.name ? true : undefined}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">{t('auth.ui.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                required
                                tabIndex={2}
                                autoComplete="email"
                                name="email"
                                aria-invalid={errors.email ? true : undefined}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('auth.ui.password')}
                            </Label>
                            <PasswordInput
                                id="password"
                                required
                                tabIndex={3}
                                autoComplete="new-password"
                                name="password"
                                aria-describedby="password-hint"
                                aria-invalid={
                                    errors.password ? true : undefined
                                }
                            />
                            <p
                                id="password-hint"
                                className="text-xs text-muted-foreground"
                            >
                                {passwordHint}
                            </p>
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('auth.ui.password_confirmation')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                required
                                tabIndex={4}
                                autoComplete="new-password"
                                name="password_confirmation"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 w-full"
                            tabIndex={5}
                            data-test="register-user-button"
                        >
                            {processing && <Spinner />}
                            {t('auth.ui.register_submit')}
                        </Button>
                    </div>

                    <div className="text-center text-sm text-muted-foreground">
                        <TextLink href={route('login')} tabIndex={6}>
                            {t('auth.ui.login_link')}
                        </TextLink>
                    </div>
                </>
            )}
        </Form>
    );
}

Register.layout = {
    title: 'auth.ui.register_title',
};
