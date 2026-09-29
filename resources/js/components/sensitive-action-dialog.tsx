import { router, usePage } from '@inertiajs/react';
import type { Method, RequestPayload } from '@inertiajs/core';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';

const noErrors = (errors: Record<string, string> | undefined): boolean =>
    Object.keys(errors ?? {}).length === 0;

/**
 * Confere o PEDIDO de uma ação sensível no servidor (papel, formulário,
 * senha de transação definida) sem mandar código: `ok` só roda se ele passou
 * — aí a tela abre a confirmação. Os erros aparecem no formulário.
 */
export function checkSensitiveAction(
    codeUrl: string,
    data: RequestPayload,
    ok: () => void,
): void {
    router.post(
        codeUrl,
        { ...(data as Record<string, unknown>), stage: 'check' },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                if (noErrors(page.props.errors)) {
                    ok();
                }
            },
        },
    );
}

/**
 * CONFIRMAÇÃO DE AÇÃO SENSÍVEL — o modal do painel (o
 * `sensitive-action-modal` do starter Livewire): senha de transação → código
 * por e-mail → a operação.
 *
 * O navegador manda só a senha (para `codeUrl`, com `stage=send`) e depois o
 * código (para `confirmUrl`, com os mesmos dados do pedido). O token de ação
 * sensível nasce e morre no servidor, na requisição da operação — ele nunca
 * chega aqui.
 */
export function SensitiveActionDialog({
    open,
    onClose,
    description,
    codeUrl,
    confirmUrl,
    confirmMethod = 'post',
    data = {},
    onConfirmed,
}: {
    open: boolean;
    onClose: () => void;
    description: string | null;
    codeUrl: string;
    confirmUrl: string;
    confirmMethod?: Method;
    data?: Record<string, unknown>;
    onConfirmed?: () => void;
}) {
    const { t } = useTrans();
    const { errors } = usePage().props;

    const [step, setStep] = useState<'password' | 'code'>('password');
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');
    const [processing, setProcessing] = useState(false);

    const close = () => {
        setStep('password');
        setPassword('');
        setCode('');
        onClose();
    };

    // Um erro do pedido (fora da senha e do código) também aparece aqui.
    const otherError = Object.entries(errors ?? {}).find(
        ([key]) => key !== 'transaction_password' && key !== 'code',
    )?.[1];

    const sendCode = () => {
        router.post(
            codeUrl,
            { ...data, stage: 'send', transaction_password: password },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: (page) => {
                    if (noErrors(page.props.errors)) {
                        setStep('code');
                        setCode('');
                    }
                },
            },
        );
    };

    const confirm = () => {
        router.visit(confirmUrl, {
            method: confirmMethod,
            data: { ...data, code },
            preserveScroll: true,
            preserveState: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: (page) => {
                if (noErrors(page.props.errors)) {
                    setStep('password');
                    setPassword('');
                    setCode('');
                    onConfirmed?.();
                }
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && close()}>
            <DialogContent data-sensitive-dialog>
                <DialogHeader>
                    <DialogTitle>{t('panel.sensitive.heading')}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>

                {step === 'password' ? (
                    <form
                        className="grid gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            sendCode();
                        }}
                    >
                        <p className="text-sm text-muted-foreground">
                            {t('panel.sensitive.password_hint')}
                        </p>
                        <Label htmlFor="sensitive_transaction_password">
                            {t('auth.ui.transaction_password_title')}
                        </Label>
                        <PasswordInput
                            id="sensitive_transaction_password"
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                            autoComplete="off"
                            autoFocus
                            aria-invalid={
                                (errors.transaction_password ?? otherError)
                                    ? true
                                    : undefined
                            }
                        />
                        <InputError
                            message={errors.transaction_password ?? otherError}
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={close}
                            >
                                {t('panel.common.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || password === ''}
                                data-sensitive-send
                            >
                                {processing && <Spinner />}
                                {t('panel.sensitive.send_code')}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : (
                    <form
                        className="grid gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            confirm();
                        }}
                    >
                        <p className="text-sm text-muted-foreground">
                            {t('panel.sensitive.code_hint')}
                        </p>
                        <Label htmlFor="sensitive_code">
                            {t('panel.sensitive.code')}
                        </Label>
                        <Input
                            id="sensitive_code"
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            autoComplete="one-time-code"
                            autoFocus
                            aria-invalid={
                                (errors.code ??
                                errors.transaction_password ??
                                otherError)
                                    ? true
                                    : undefined
                            }
                        />
                        <InputError
                            message={
                                errors.code ??
                                errors.transaction_password ??
                                otherError
                            }
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={sendCode}
                                disabled={processing}
                            >
                                {t('auth.two_factor.resend')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || code.length !== 6}
                                data-sensitive-confirm
                            >
                                {processing && <Spinner />}
                                {t('panel.sensitive.confirm')}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
