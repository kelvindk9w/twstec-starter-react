import { Head } from '@inertiajs/react';

/**
 * Título da página do painel (e da aba), com a linha de apoio.
 */
export function PageHeader({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <header className="space-y-1">
            <Head title={title} />
            <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
