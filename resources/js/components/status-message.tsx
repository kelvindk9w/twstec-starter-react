import { cn } from '@/lib/utils';

/**
 * Aviso fixo na tela (não some como o toast): confirmação ou explicação que
 * precisa ficar ao lado do que a pessoa vai fazer em seguida.
 */
export function StatusMessage({
    message,
    tone = 'success',
    className,
    ...props
}: {
    message?: string | null;
    tone?: 'success' | 'warning';
    className?: string;
} & React.HTMLAttributes<HTMLDivElement>) {
    if (!message) {
        return null;
    }

    return (
        <div
            role="status"
            className={cn(
                'rounded-md border px-3 py-2 text-sm',
                tone === 'success'
                    ? 'border-green-600/30 bg-green-50 text-green-800 dark:bg-green-950/40 dark:text-green-300'
                    : 'border-amber-600/30 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
                className,
            )}
            {...props}
        >
            {message}
        </div>
    );
}
