import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

type Props = {
    email: string;
    codeTtlMinutes: number;
};

/**
 * Segundo passo do login (código por e-mail). Quem está aqui acertou a senha
 * mas AINDA não entrou. Duas saídas: pedir outro código (com intervalo no
 * servidor) e voltar ao login (o código enviado deixa de valer).
 */
export default function TwoFactorChallenge({ email, codeTtlMinutes }: Props) {
    const { t } = useTrans();
    const { route } = useRoute();

    return (
        <div className="space-y-6">
            <p
                className="text-center text-sm text-muted-foreground"
                data-two-factor-intro
            >
                {t('auth.two_factor.intro', { email, minutes: codeTtlMinutes })}
            </p>

            <Form
                action={route('two-factor.challenge')}
                method="post"
                resetOnError
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
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
                                aria-invalid={errors.code ? true : undefined}
                            />
                            <InputError message={errors.code} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('auth.two_factor.submit')}
                        </Button>
                    </>
                )}
            </Form>

            <div className="space-y-2">
                <Form action={route('two-factor.resend')} method="post">
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="secondary"
                            className="w-full"
                            disabled={processing}
                            data-two-factor-resend
                        >
                            {t('auth.two_factor.resend')}
                        </Button>
                    )}
                </Form>
                <p className="text-center text-xs text-muted-foreground">
                    {t('auth.two_factor.resend_hint')}
                </p>
            </div>

            <Form action={route('two-factor.cancel')} method="post">
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="ghost"
                        className="w-full"
                        disabled={processing}
                    >
                        {t('auth.two_factor.cancel')}
                    </Button>
                )}
            </Form>
        </div>
    );
}

TwoFactorChallenge.layout = {
    title: 'auth.two_factor.title',
};
