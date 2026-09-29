import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { Translations } from '@/types';

/**
 * Traduções nas páginas React — os MESMOS arquivos de tradução do Laravel
 * (lang/{pt_BR,en,es}), enviados pelo servidor uma vez por idioma
 * (App\Support\FrontTranslations). Nenhum texto escrito no TypeScript.
 *
 * `t('auth.ui.login_title')` e `t('auth.two_factor.intro', { email, minutes })`,
 * com as substituições no formato do Laravel (`:nome`, `:Nome`, `:NOME`).
 * Chave que não existe volta a própria chave — visível na tela, como no
 * `__()` do Laravel.
 */
export type Replacements = Record<string, string | number>;

export function translate(
    translations: Translations,
    key: string,
    replacements: Replacements = {},
): string {
    const value = key
        .split('.')
        .reduce<unknown>(
            (node, segment) =>
                node !== null && typeof node === 'object'
                    ? (node as Record<string, unknown>)[segment]
                    : undefined,
            translations,
        );

    if (typeof value !== 'string') {
        return key;
    }

    return Object.entries(replacements)
        .sort(([a], [b]) => b.length - a.length)
        .reduce((line, [name, raw]) => {
            const text = String(raw);

            return line
                .replaceAll(`:${name.toUpperCase()}`, text.toUpperCase())
                .replaceAll(
                    `:${name.charAt(0).toUpperCase()}${name.slice(1)}`,
                    text.charAt(0).toUpperCase() + text.slice(1),
                )
                .replaceAll(`:${name}`, text);
        }, value);
}

/**
 * Escolhe a forma pelo número, no formato do `trans_choice` do Laravel:
 * `{1} :count pessoa|[2,*] :count pessoas` (valor exato ou intervalo, `*` sem
 * limite) ou só `singular|plural`. `:count` entra sozinho.
 */
export function choose(line: string, count: number): string {
    const segments = line.split('|');

    for (const segment of segments) {
        const exact = segment.match(/^\s*\{(-?\d+)\}\s?([\s\S]*)$/);

        if (exact && Number(exact[1]) === count) {
            return exact[2];
        }

        const range = segment.match(
            /^\s*\[(-?\d+|\*),\s*(-?\d+|\*)\]\s?([\s\S]*)$/,
        );

        if (
            range &&
            (range[1] === '*' || count >= Number(range[1])) &&
            (range[2] === '*' || count <= Number(range[2]))
        ) {
            return range[3];
        }
    }

    const plain = segments.map((segment) =>
        segment.replace(/^\s*(\{-?\d+\}|\[(-?\d+|\*),\s*(-?\d+|\*)\])\s?/, ''),
    );

    return plain.length > 1 && count !== 1 ? plain[1] : plain[0];
}

export function useTrans(): {
    t: (key: string, replacements?: Replacements) => string;
    tc: (key: string, count: number, replacements?: Replacements) => string;
    locale: string;
} {
    const { translations, app } = usePage().props;

    const t = useCallback(
        (key: string, replacements?: Replacements) =>
            translate(translations, key, replacements),
        [translations],
    );

    const tc = useCallback(
        (key: string, count: number, replacements: Replacements = {}) => {
            const line = translate(translations, key);

            return line === key
                ? key
                : translate({ line: choose(line, count) }, 'line', {
                      count,
                      ...replacements,
                  });
        },
        [translations],
    );

    return { t, tc, locale: app.locale };
}
