import { ShieldAlert } from 'lucide-react';
import { CopyButton } from '@/components/copy-button';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';

/**
 * VISUALIZAÇÃO ÚNICA DA SECRETA de uma chave de API: exibida uma vez, sem
 * recuperação. O peso visual é proposital — é a única tela do kit em que
 * perder a atenção de quem usa custa uma credencial. "Já guardei" apaga o
 * valor da tela; ele nunca mais volta (no banco só existe o hash).
 */
export function SecretReveal({
    publicKey,
    secret,
    onDone,
}: {
    publicKey: string;
    secret: string;
    onDone: () => void;
}) {
    const { t } = useTrans();

    return (
        <section
            className="rounded-xl border-2 border-amber-400 bg-amber-50 p-5 dark:border-amber-500 dark:bg-amber-950/40"
            data-secret-reveal
        >
            <div className="flex items-start gap-3">
                <ShieldAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                <div className="min-w-0">
                    <h2 className="text-lg font-semibold text-amber-900 dark:text-amber-100">
                        {t('panel.api_keys.secret_heading')}
                    </h2>
                    <p className="mt-1 text-sm font-medium text-amber-800 dark:text-amber-200">
                        {t('panel.api_keys.secret_warning')}
                    </p>
                </div>
            </div>

            <dl className="mt-4 space-y-3">
                <div>
                    <dt className="text-xs font-medium tracking-wide text-amber-700 uppercase dark:text-amber-300">
                        {t('panel.api_keys.public_key')}
                    </dt>
                    <dd className="mt-1 flex flex-wrap items-center gap-2">
                        <code
                            className="rounded bg-background px-2 py-1 text-sm break-all"
                            data-revealed-public-key
                        >
                            {publicKey}
                        </code>
                        <CopyButton
                            value={publicKey}
                            label={t('panel.api_keys.copy')}
                            copiedLabel={t('panel.api_keys.copied')}
                        />
                    </dd>
                </div>
                <div>
                    <dt className="text-xs font-medium tracking-wide text-amber-700 uppercase dark:text-amber-300">
                        {t('panel.api_keys.secret_key')}
                    </dt>
                    <dd className="mt-1 flex flex-wrap items-center gap-2">
                        <code
                            className="rounded bg-background px-2 py-1 text-sm font-semibold break-all"
                            data-revealed-secret-key
                        >
                            {secret}
                        </code>
                        <CopyButton
                            value={secret}
                            label={t('panel.api_keys.copy')}
                            copiedLabel={t('panel.api_keys.copied')}
                            variant="default"
                        />
                    </dd>
                </div>
            </dl>

            <Button
                type="button"
                variant="outline"
                className="mt-5"
                onClick={onDone}
                data-secret-done
            >
                {t('panel.api_keys.secret_done')}
            </Button>
        </section>
    );
}
