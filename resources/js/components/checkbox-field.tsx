import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * Uma caixa de marcar com o rótulo clicável (escopos, projetos).
 */
export function CheckboxField({
    id,
    label,
    checked,
    onChange,
    className,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    className?: string;
}) {
    return (
        <div className={cn('flex min-h-9 items-center gap-2', className)}>
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
            />
            <Label htmlFor={id} className="cursor-pointer font-normal">
                {label}
            </Label>
        </div>
    );
}

/** Liga/desliga um valor numa lista (marcar/desmarcar). */
export function toggleIn(list: string[], value: string, on: boolean): string[] {
    return on
        ? Array.from(new Set([...list, value]))
        : list.filter((item) => item !== value);
}
