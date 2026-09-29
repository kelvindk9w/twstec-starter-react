import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { IconAction } from '@/components/icon-action';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

/**
 * Copiar um valor (chave pública, secreta). `iconOnly`: só o ícone com
 * tooltip (nas linhas da lista), com o aviso num toast.
 */
export function CopyButton({
    value,
    label,
    copiedLabel,
    iconOnly = false,
    variant = 'outline',
}: {
    value: string;
    label: string;
    copiedLabel: string;
    iconOnly?: boolean;
    variant?: 'outline' | 'default';
}) {
    const [copied, copy] = useClipboard();
    const done = copied === value;

    const onCopy = async () => {
        if ((await copy(value)) && iconOnly) {
            toast.success(copiedLabel, { id: `copied-${value}` });
        }
    };

    if (iconOnly) {
        return (
            <IconAction
                icon={done ? Check : Copy}
                label={label}
                onClick={onCopy}
                data-copy
            />
        );
    }

    return (
        <Button type="button" size="sm" variant={variant} onClick={onCopy}>
            {done ? <Check /> : <Copy />}
            {done ? copiedLabel : label}
        </Button>
    );
}
