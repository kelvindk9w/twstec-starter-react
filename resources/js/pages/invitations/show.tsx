import { Form, Link, router } from '@inertiajs/react';
import { MailCheck, TriangleAlert } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

type Props = {
    invitation: {
        state: string;
        mode: 'accept' | 'login' | 'register' | null;
        accountName: string | null;
        inviterName: string | null;
        roleLabel: string | null;
        email: string | null;
        expiresAt: string | null;
        message: string | null;
    };
    error: string | null;
    loggedIn: boolean;
    passwordHint: string;
};

/**
 * Tela do LINK DE CONVITE (pública), no layout das telas de autenticação — a
 * mesma do starter Livewire. O que aparece vem do pacote de contas
 * (InvitationPreview):
 * - pendente + logado com o e-mail do convite → Aceitar / Recusar;
 * - pendente + deslogado, o e-mail já tem conta → Entrar para aceitar (o
 *   login volta para cá);
 * - pendente + deslogado, sem conta → o formulário que CRIA o acesso (o
 *   e-mail é o do convite, não se escolhe);
 * - qualquer outro estado → só o motivo. Com e-mail diferente, NENHUM dado
 *   da conta chega à tela.
 *
 * O token está no endereço desta página (não nas props): os envios são
 * montados a partir dele.
 */
export default function InvitationShow({
    invitation,
    error,
    loggedIn,
    passwordHint,
}: Props) {
    const { t } = useTrans();
    const { route, currentParam } = useRoute();
    const token = currentParam('invitations.show', 'token') ?? '';
    const pending = invitation.state === 'pending';

    const decline = () =>
        router.post(
            route('invitations.decline', { token }),
            {},
            { preserveScroll: true },
        );

    return (
        <div
            className="flex flex-col gap-6"
            data-invitation-state={invitation.state}
            data-invitation-mode={invitation.mode ?? undefined}
        >
            {error && (
                <Alert variant="destructive" data-invitation-error>
                    <TriangleAlert />
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {pending ? (
                <>
                    <p
                        className="text-sm text-muted-foreground"
                        data-invitation-intro
                    >
                        {t('panel.invitation.intro', {
                            inviter:
                                invitation.inviterName ??
                                t('panel.invitation.someone'),
                            account: invitation.accountName ?? '',
                            role: invitation.roleLabel ?? '',
                        })}
                    </p>

                    <dl className="space-y-2 rounded-lg border bg-muted/40 p-3 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                {t('panel.invitation.email')}
                            </dt>
                            <dd
                                className="font-medium break-all"
                                data-invitation-email
                            >
                                {invitation.email}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                {t('panel.invitation.expires')}
                            </dt>
                            <dd className="font-medium">
                                {invitation.expiresAt}
                            </dd>
                        </div>
                    </dl>

                    {invitation.mode === 'accept' && (
                        <Form
                            action={route('invitations.accept', { token })}
                            method="post"
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                    data-invitation-accept
                                >
                                    {processing ? <Spinner /> : <MailCheck />}
                                    {t('panel.invitation.accept')}
                                </Button>
                            )}
                        </Form>
                    )}

                    {invitation.mode === 'login' && (
                        <div className="grid gap-3">
                            <p className="text-sm text-muted-foreground">
                                {t('panel.invitation.login_hint')}
                            </p>
                            <Button
                                asChild
                                className="w-full"
                                data-invitation-login
                            >
                                <Link href={route('login')}>
                                    {t('panel.invitation.login')}
                                </Link>
                            </Button>
                        </div>
                    )}

                    {invitation.mode === 'register' && (
                        <Form
                            action={route('invitations.register', { token })}
                            method="post"
                            resetOnError={['password', 'password_confirmation']}
                            className="grid gap-4"
                            data-invitation-register
                        >
                            {({ processing, errors }) => (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        {t('panel.invitation.register_hint')}
                                    </p>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {t('auth.ui.name')}
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            autoComplete="name"
                                            aria-invalid={
                                                errors.name ? true : undefined
                                            }
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            {t('auth.ui.password')}
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                            aria-describedby="password-hint"
                                            aria-invalid={
                                                errors.password
                                                    ? true
                                                    : undefined
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
                                            name="password_confirmation"
                                            required
                                            autoComplete="new-password"
                                        />
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('panel.invitation.register')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}

                    <Button
                        type="button"
                        variant="ghost"
                        className="w-full"
                        onClick={decline}
                        data-invitation-decline
                    >
                        {t('panel.invitation.decline')}
                    </Button>
                </>
            ) : (
                <>
                    <Alert data-invitation-unavailable>
                        <TriangleAlert />
                        <AlertDescription>
                            {invitation.message}
                        </AlertDescription>
                    </Alert>

                    {loggedIn ? (
                        invitation.state === 'wrong_email' ? (
                            <Form action={route('logout')} method="post">
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="w-full"
                                >
                                    {t('auth.ui.logout')}
                                </Button>
                            </Form>
                        ) : (
                            <Button
                                asChild
                                variant="outline"
                                className="w-full"
                            >
                                <Link href={route('dashboard')}>
                                    {t('panel.invitation.go_to_panel')}
                                </Link>
                            </Button>
                        )
                    ) : (
                        <Button asChild variant="outline" className="w-full">
                            <Link href={route('login')}>
                                {t('auth.ui.login_link')}
                            </Link>
                        </Button>
                    )}
                </>
            )}
        </div>
    );
}

InvitationShow.layout = {
    title: 'panel.invitation.title',
};
