import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export type IconActionTone = 'neutral' | 'green' | 'amber' | 'red' | 'blue';

const tones: Record<IconActionTone, string> = {
    neutral: 'text-muted-foreground hover:text-foreground',
    green: 'text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-950/50',
    amber: 'text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-950/50',
    red: 'text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/50',
    blue: 'text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-400 dark:hover:bg-sky-950/50',
};

/**
 * Ação de linha/cartão SÓ COM ÍCONE: a cor diz o tipo (verde promove, âmbar
 * rebaixa, vermelho destrói) e o nome vem no tooltip e no rótulo acessível —
 * o padrão das listas do painel (o <x-icon-button> do starter Livewire).
 */
export function IconAction({
    icon: Icon,
    label,
    tone = 'neutral',
    className,
    ...props
}: {
    icon: LucideIcon;
    label: string;
    tone?: IconActionTone;
} & Omit<ComponentProps<typeof Button>, 'children'>) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={label}
                    className={cn('size-9 sm:size-8', tones[tone], className)}
                    {...props}
                >
                    <Icon className="size-4" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
