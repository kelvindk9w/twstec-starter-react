import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

type Props = {
    token: string;
    email: string;
    passwordHint: string;
};

/**
 * Nova senha, pelo link do e-mail. O token é o do próprio link (volta no
 * envio, como no campo oculto do starter Livewire).
 */
export default function ResetPassword({ token, email, passwordHint }: Props) {
    const { t } = useTrans();
    const { route } = useRoute();

    return (
        <Form
            action={route('password.update')}
            method="post"
            transform={(data) => ({ ...data, token })}
            resetOnSuccess={['password', 'password_confirmation']}
        >
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('auth.ui.email')}</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autoComplete="email"
                            defaultValue={email}
                            required
                            aria-invalid={errors.email ? true : undefined}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">
                            {t('auth.ui.new_password')}
                        </Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            autoComplete="new-password"
                            autoFocus
                            required
                            aria-describedby="password-hint"
                            aria-invalid={errors.password ? true : undefined}
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
                            name="password_confirmation"
                            autoComplete="new-password"
                            required
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button
                        type="submit"
                        className="mt-2 w-full"
                        disabled={processing}
                        data-test="reset-password-button"
                    >
                        {processing && <Spinner />}
                        {t('auth.ui.reset_submit')}
                    </Button>
                </div>
            )}
        </Form>
    );
}

ResetPassword.layout = {
    title: 'auth.ui.reset_title',
};
