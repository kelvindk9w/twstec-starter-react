import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

/**
 * Definir/alterar a senha de TRANSAÇÃO. O envio é do pacote twstec/kit-auth
 * (TransactionPasswordController: hash separado, diferente da senha de
 * login, a atual exigida na troca) — a mesma regra usada pelo Livewire.
 */
export function TransactionPasswordForm({
    hasTransactionPassword,
    submitLabel,
}: {
    hasTransactionPassword: boolean;
    submitLabel: string;
}) {
    const { t } = useTrans();
    const { route } = useRoute();

    return (
        <Form
            action={route('transaction-password.update')}
            method="put"
            options={{ preserveScroll: true }}
            resetOnSuccess
            resetOnError
            className="grid max-w-md gap-4"
        >
            {({ processing, errors }) => (
                <>
                    {hasTransactionPassword && (
                        <div className="grid gap-2">
                            <Label htmlFor="current_transaction_password">
                                {t('auth.ui.current_transaction_password')}
                            </Label>
                            <PasswordInput
                                id="current_transaction_password"
                                name="current_transaction_password"
                                autoComplete="off"
                                aria-invalid={
                                    errors.current_transaction_password
                                        ? true
                                        : undefined
                                }
                            />
                            <InputError
                                message={errors.current_transaction_password}
                            />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="transaction_password">
                            {t('auth.ui.new_transaction_password')}
                        </Label>
                        <PasswordInput
                            id="transaction_password"
                            name="transaction_password"
                            autoComplete="off"
                            aria-invalid={
                                errors.transaction_password ? true : undefined
                            }
                        />
                        <InputError message={errors.transaction_password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="transaction_password_confirmation">
                            {t('auth.ui.password_confirmation')}
                        </Label>
                        <PasswordInput
                            id="transaction_password_confirmation"
                            name="transaction_password_confirmation"
                            autoComplete="off"
                        />
                        <InputError
                            message={errors.transaction_password_confirmation}
                        />
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
