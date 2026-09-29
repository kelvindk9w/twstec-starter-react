import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

export type RouteParams = Record<string, string | number>;

/**
 * Preenche os marcadores `{nome}` (e `{nome?}`) de um modelo de rota.
 * Marcador sem valor é erro de programação: falha alto.
 */
export function fillRoute(
    name: string,
    template: string,
    params: RouteParams = {},
): string {
    const url = template.replace(/\{([^}?]+)\??\}/g, (_match, key: string) => {
        const value = params[key];

        if (value === undefined) {
            throw new Error(
                `Rota ${name}: falta o parâmetro "${key}" no front`,
            );
        }

        return encodeURIComponent(String(value));
    });

    return url;
}

/**
 * O valor de um parâmetro no endereço ATUAL, lido pelo modelo de uma rota
 * (ex.: o token do link de convite, que está na barra de endereço e não vai
 * para as props). `null` se o endereço não tem o formato da rota.
 */
export function matchRoute(
    template: string,
    url: string,
    key: string,
): string | null {
    const names: string[] = [];
    const pattern = template
        .split(/(\{[^}]+\})/)
        .map((part) => {
            const found = part.match(/^\{([^}?]+)\??\}$/);

            if (found) {
                names.push(found[1]);

                return '([^/]+)';
            }

            return part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        })
        .join('');
    const path = url.split(/[?#]/)[0];
    const match = new RegExp(`^${pattern}/?$`).exec(path);
    const index = names.indexOf(key);

    if (!match || index === -1) {
        return null;
    }

    return decodeURIComponent(match[index + 1]);
}

/**
 * Endereço de uma rota pelo NOME — o mapa vem do servidor
 * (App\Support\FrontRoutes), só com as rotas que existem nesta instalação.
 * Nenhuma URL escrita no TypeScript. Rota com parâmetro chega como MODELO
 * (`/api-keys/{key}/rotate`) e é preenchida aqui:
 * `route('panel.api-keys.rotate', { key: uuid })`.
 *
 * Pedir uma rota que não está no mapa é erro de programação (a rota não
 * existe ou faltou na lista do FrontRoutes): falha alto em vez de montar um
 * link quebrado.
 */
export function useRoute(): {
    route: (name: string, params?: RouteParams) => string;
    has: (name: string) => boolean;
    currentParam: (name: string, key: string) => string | null;
} {
    const { props, url } = usePage();
    const { routes } = props;

    const route = useCallback(
        (name: string, params?: RouteParams): string => {
            const template = routes[name];

            if (template === undefined) {
                throw new Error(`Rota desconhecida no front: ${name}`);
            }

            return fillRoute(name, template, params);
        },
        [routes],
    );

    const has = useCallback(
        (name: string): boolean => routes[name] !== undefined,
        [routes],
    );

    const currentParam = useCallback(
        (name: string, key: string): string | null => {
            const template = routes[name];

            return template === undefined
                ? null
                : matchRoute(template, url, key);
        },
        [routes, url],
    );

    return { route, has, currentParam };
}
