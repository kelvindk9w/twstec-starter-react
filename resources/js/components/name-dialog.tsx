import { router, usePage } from '@inertiajs/react';
import type { Method } from '@inertiajs/core';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';

/**
 * Dar ou trocar um NOME (projeto, conta) num diálogo: um campo, salvar e
 * cancelar. O erro do servidor aparece no campo.
 */
export function NameDialog({
    open,
    onClose,
    title,
    label,
    initial,
    url,
    method = 'patch',
    maxLength = 255,
    placeholder,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    label: string;
    initial: string;
    url: string;
    method?: Method;
    maxLength?: number;
    placeholder?: string;
}) {
    const { t } = useTrans();
    const { errors } = usePage().props;
    const [name, setName] = useState(initial);
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        router.visit(url, {
            method,
            data: { name },
            preserveScroll: true,
            preserveState: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: (page) => {
                if (Object.keys(page.props.errors ?? {}).length === 0) {
                    onClose();
                }
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="dialog-name">{label}</Label>
                        <Input
                            id="dialog-name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            maxLength={maxLength}
                            placeholder={placeholder}
                            autoFocus
                            aria-invalid={errors.name ? true : undefined}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            {t('panel.common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t('panel.common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
