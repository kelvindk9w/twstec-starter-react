import { useCallback, useState } from 'react';

export type ViewMode = 'table' | 'cards';

const read = (key: string): ViewMode => {
    try {
        return window.localStorage.getItem(key) === 'cards' ? 'cards' : 'table';
    } catch {
        return 'table';
    }
};

/**
 * Tabela ou cartões numa lista do painel — escolha de quem vê, guardada neste
 * navegador (conveniência: sem ela, a lista abre em tabela). No celular a
 * tabela já vira cartões e o alternador some.
 */
export function useViewMode(
    list: string,
): [ViewMode, (mode: ViewMode) => void] {
    const key = `panel.view.${list}`;
    const [mode, setMode] = useState<ViewMode>(() => read(key));

    const change = useCallback(
        (next: ViewMode) => {
            setMode(next);

            try {
                window.localStorage.setItem(key, next);
            } catch {
                // Sem armazenamento (janela privada): vale só nesta página.
            }
        },
        [key],
    );

    return [mode, change];
}
