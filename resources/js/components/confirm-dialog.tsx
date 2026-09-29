import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';

/**
 * Confirmação de uma ação (remover, revogar, sair, excluir): o que acontece,
 * cancelar e confirmar. Destrutiva = botão vermelho.
 */
export function ConfirmDialog({
    open,
    onClose,
    title,
    description,
    confirmLabel,
    onConfirm,
    processing = false,
    destructive = true,
    children,
    confirmTest,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string;
    confirmLabel: string;
    onConfirm: () => void;
    processing?: boolean;
    destructive?: boolean;
    children?: ReactNode;
    confirmTest?: string;
}) {
    const { t } = useTrans();

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                {children}
                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={onClose}>
                        {t('panel.common.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={processing}
                        onClick={onConfirm}
                        data-confirm={confirmTest}
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
