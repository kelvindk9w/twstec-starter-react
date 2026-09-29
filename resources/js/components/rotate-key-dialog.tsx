import { usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTrans } from '@/lib/i18n';

const graceLabels: Record<number, string> = {
    0: 'panel.api_keys.grace_immediate',
    60: 'panel.api_keys.grace_1h',
    1440: 'panel.api_keys.grace_24h',
    10080: 'panel.api_keys.grace_7d',
};

/**
 * ROTACIONAR uma chave: quando a atual deixa de funcionar (na hora ou depois
 * de um período de transição). Confirmar abre a confirmação de segurança —
 * este diálogo fecha antes, para os dois não se empilharem.
 */
export function RotateKeyDialog({
    open,
    onClose,
    options,
    value,
    onChange,
    onConfirm,
}: {
    open: boolean;
    onClose: () => void;
    options: number[];
    value: number;
    onChange: (minutes: number) => void;
    onConfirm: () => void;
}) {
    const { t } = useTrans();
    const { errors } = usePage().props;

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent data-rotate-dialog>
                <DialogHeader>
                    <DialogTitle>
                        {t('panel.api_keys.rotate_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('panel.api_keys.rotate_warning')}
                    </DialogDescription>
                </DialogHeader>
                <p className="text-xs text-muted-foreground">
                    {t('panel.api_keys.grace_hint')}
                </p>
                <div className="grid gap-2" role="radiogroup">
                    {options.map((minutes) => (
                        <label
                            key={minutes}
                            className="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition-colors hover:bg-accent has-[:checked]:border-primary"
                        >
                            <input
                                type="radio"
                                name="grace_minutes"
                                value={minutes}
                                checked={value === minutes}
                                onChange={() => onChange(minutes)}
                                className="size-4 accent-primary"
                            />
                            {t(
                                graceLabels[minutes] ??
                                    'panel.api_keys.grace_immediate',
                            )}
                        </label>
                    ))}
                </div>
                <InputError message={errors.rotate ?? errors.grace_minutes} />
                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={onClose}>
                        {t('panel.common.cancel')}
                    </Button>
                    <Button
                        type="button"
                        onClick={onConfirm}
                        data-rotate-confirm
                    >
                        {t('panel.common.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
