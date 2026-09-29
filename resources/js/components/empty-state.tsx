import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Lista vazia: o ícone do assunto, o que falta e a ação que resolve.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    children,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center">
            <span className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-6" />
            </span>
            <div className="space-y-1">
                <p className="font-medium">{title}</p>
                <p className="max-w-md text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            {children}
        </div>
    );
}
