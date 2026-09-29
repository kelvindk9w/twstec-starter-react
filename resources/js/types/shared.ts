import type { ReactNode } from 'react';

/**
 * As props que TODA página recebe do servidor
 * (App\Http\Middleware\HandleInertiaRequests). Lista fechada: nada de
 * credencial passa por aqui.
 */
export type User = {
    uuid: string;
    code: string;
    name: string;
    email: string;
    locale: string;
    theme: Theme;
    avatarUrl: string | null;
    emailVerified: boolean;
    hasTransactionPassword: boolean;
    twoFactorEnabled: boolean;
};

export type Theme = 'light' | 'dark' | 'system';

export type Locale = {
    code: string;
    label: string;
    url: string;
};

export type NavItem = {
    label: string;
    href: string;
    icon: string;
    active: boolean;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};

export type OptionalModule = 'accounts' | 'uploads' | 'admin';

/** Papel fixo de uma pessoa numa conta (twstec/kit-accounts). */
export type AccountRole = 'owner' | 'admin' | 'member';

export type AccountMenuEntry = {
    uuid: string;
    name: string;
    role: AccountRole;
    /** Papel já traduzido (na conta pessoal, com "Conta pessoal"). */
    roleLabel: string;
    personal: boolean;
};

/** O seletor de conta (App\Support\AccountMenu) — só com o pacote de contas. */
export type AccountMenu = {
    current: AccountMenuEntry;
    accounts: (AccountMenuEntry & { current: boolean })[];
};

export type Translations = Record<string, unknown>;

export type SharedProps = {
    app: {
        name: string;
        logoUrl: string | null;
        locale: string;
        locales: Locale[];
    };
    auth: {
        user: User | null;
    };
    kit: {
        modules: Record<OptionalModule, boolean>;
    };
    navigation: NavGroup[];
    accountMenu: AccountMenu | null;
    routes: Record<string, string>;
    flash: {
        status: string | null;
        verification_error: string | null;
    };
    translations: Translations;
    sidebarOpen: boolean;
};

export type BreadcrumbItem = {
    /** Chave de tradução (ex.: `panel.nav.profile`). */
    title: string;
    /** Nome da rota (ex.: `panel.profile`). */
    route: string;
};

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AuthLayoutProps = {
    children?: ReactNode;
    /** Chave de tradução do título. */
    title?: string;
    /** Chave de tradução da descrição. */
    description?: string;
};

export type AppVariant = 'header' | 'sidebar';

/**
 * Dado de UMA resposta só do Inertia (`Inertia::flash`, `page.flash`): o
 * Inertia não o guarda no histórico do navegador. É o único caminho da
 * secreta de uma chave de API recém-criada ou rotacionada.
 */
export type FlashData = {
    revealedKey?: {
        publicKey: string;
        secret: string;
    };
};
