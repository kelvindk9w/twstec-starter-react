import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { AccountRole } from '@/types';

const tones: Record<AccountRole, string> = {
    owner: 'border-transparent bg-primary text-primary-foreground',
    admin: 'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    member: 'border-transparent bg-muted text-muted-foreground',
};

/**
 * O papel de uma pessoa numa conta (o rótulo já vem traduzido do servidor).
 */
export function RoleBadge({
    role,
    label,
    className,
    ...props
}: {
    role: AccountRole;
    label: string;
} & Omit<ComponentProps<'span'>, 'children'>) {
    return (
        <Badge className={cn(tones[role], className)} {...props}>
            {label}
        </Badge>
    );
}
