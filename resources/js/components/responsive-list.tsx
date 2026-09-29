import type { ReactNode } from 'react';
import type { ViewMode } from '@/hooks/use-view-mode';

/**
 * Uma lista do painel em TABELA ou em CARTÕES (o alternador escolhe no
 * desktop); no celular, sempre cartões — a tabela não cabe.
 */
export function ResponsiveList({
    mode,
    table,
    cards,
}: {
    mode: ViewMode;
    table: ReactNode;
    cards: ReactNode;
}) {
    if (mode === 'cards') {
        return (
            <div
                className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                data-list-cards
            >
                {cards}
            </div>
        );
    }

    return (
        <>
            <div className="hidden sm:block" data-list-table>
                {table}
            </div>
            <div className="grid gap-3 sm:hidden">{cards}</div>
        </>
    );
}
