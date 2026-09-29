import { http, usePage } from '@inertiajs/react';
import { useCallback, useSyncExternalStore } from 'react';
import { useRoute } from '@/lib/routes';
import type { Theme } from '@/types';

/**
 * Tema claro/escuro/sistema — a mesma regra do starter Livewire:
 * localStorage `theme` (este dispositivo) → `data-theme-default` no <html>
 * (o padrão da CONTA, quando há sessão) → `system`. O script do <head>
 * (resources/views/app.blade.php) aplica a classe antes da primeira pintura;
 * aqui fica a troca em tempo de execução e a gravação na conta
 * (POST settings.theme), para os outros dispositivos seguirem.
 */
const listeners = new Set<() => void>();
const media = (): MediaQueryList | null =>
    typeof window === 'undefined'
        ? null
        : window.matchMedia('(prefers-color-scheme: dark)');

function isTheme(value: unknown): value is Theme {
    return value === 'light' || value === 'dark' || value === 'system';
}

export function currentTheme(): Theme {
    if (typeof document === 'undefined') {
        return 'system';
    }

    let stored: string | null = null;

    try {
        stored = localStorage.getItem('theme');
    } catch {
        // storage indisponível (navegação privada restrita)
    }

    if (isTheme(stored)) {
        return stored;
    }

    const fallback = document.documentElement.dataset.themeDefault;

    return isTheme(fallback) ? fallback : 'system';
}

export function applyTheme(theme: Theme): void {
    if (typeof document === 'undefined') {
        return;
    }

    const dark =
        theme === 'dark' || (theme === 'system' && media()?.matches === true);

    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
}

export function initializeTheme(): void {
    applyTheme(currentTheme());

    media()?.addEventListener('change', () => {
        if (currentTheme() === 'system') {
            applyTheme('system');
        }
    });
}

const subscribe = (callback: () => void): (() => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

/**
 * Só o tema atual, sem gravar nada — para quem fica FORA da árvore do Inertia
 * (o <Toaster> do app.tsx) e não pode ler as props da página.
 */
export function useCurrentTheme(): Theme {
    return useSyncExternalStore(
        subscribe,
        currentTheme,
        () => 'system' as Theme,
    );
}

export function useTheme(): {
    theme: Theme;
    setTheme: (theme: Theme) => void;
} {
    const theme = useSyncExternalStore(
        subscribe,
        currentTheme,
        () => 'system' as Theme,
    );
    const { auth } = usePage().props;
    const { route, has } = useRoute();

    const setTheme = useCallback(
        (next: Theme) => {
            try {
                localStorage.setItem('theme', next);
            } catch {
                // storage indisponível: vale só nesta página
            }

            applyTheme(next);
            listeners.forEach((listener) => listener());

            // Grava o padrão da conta (JSON, com o X-XSRF-TOKEN do cliente
            // HTTP do Inertia). Falha de rede não desfaz a troca local.
            if (auth.user !== null && has('settings.theme')) {
                void http
                    .getClient()
                    .request({
                        method: 'post',
                        url: route('settings.theme'),
                        data: { theme: next },
                        headers: { Accept: 'application/json' },
                    })
                    .catch(() => undefined);
            }
        },
        [auth.user, has, route],
    );

    return { theme, setTheme };
}
