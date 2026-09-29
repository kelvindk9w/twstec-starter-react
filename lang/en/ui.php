<?php

declare(strict_types=1);

// UI strings (en). Every user-facing string goes through __().

return [

    'locale' => [
        'label' => 'Language',
        'names' => [
            'pt_BR' => 'Português (Brasil)',
            'en' => 'English',
            'es' => 'Español',
        ],
    ],

    // Navegação do site (<x-site-header>, <x-side-nav>). O painel e a landing
    // compartilham o MESMO cabeçalho — por isso estas chaves são de UI, não
    // de "landing" nem de "panel".
    'nav' => [
        'site' => 'Site navigation',
        'menu' => 'Menu',
        'open_menu' => 'Open navigation menu',
        'account' => 'My account',
        'account_menu' => 'Account menu',
        'back_to_site' => 'Back to site',
        'toggle_sidebar' => 'Show or collapse the side menu',
        'sections' => 'Sections',
    ],

    'theme' => [
        'toggle' => 'Theme: switch between system, light and dark',
        'label' => 'Theme',
        'system' => 'System',
        'light' => 'Light',
        'dark' => 'Dark',
    ],

    // Seletor de arquivo do kit (<x-file-input>): o chrome nativo do
    // <input type="file"> é traduzido pelo SISTEMA OPERACIONAL, não por nós.
    'file' => [
        'choose' => 'Choose file',
        'empty' => 'No file selected',
    ],

    'chart' => [
        'empty_title' => 'No data in this period',
    ],

    'password' => [
        'show' => 'Show password',
        'hide' => 'Hide password',
    ],

    // Validation error summary (<x-form-errors>).
    'form_errors' => [
        'title' => 'Review the highlighted fields',
    ],

    'footer' => [
        'operated_by' => 'Operated by :platform',
        'support' => 'Support',
    ],

    // Table/cards switch of the panel lists (<x-view-toggle>).
    'view_toggle' => [
        'label' => 'How the list is shown',
        'table' => 'Show as table',
        'cards' => 'Show as cards',
    ],

    // Account switcher (<x-account-switcher>).
    'account_switcher' => [
        'label' => 'Current account: :account. Switch account',
        'heading' => 'Your accounts',
        'manage' => 'Account & members',
        'create' => 'Create a company account',
    ],

    // Application console commands (routes/console.php).
    'console' => [
        'filament_assets' => 'Publishes the Filament assets when the /admin panel (twstec/kit-admin) is installed',
        'filament_skipped' => 'Filament is not installed (the /admin panel is optional): nothing to publish.',
    ],

    // Starter React: textos dos componentes de interface (shadcn/ui) e o
    // aviso de sessão vencida numa visita do Inertia (419).
    'close' => 'Close',
    'more' => 'More',
    'loading' => 'Loading',
    'session_expired' => 'Your session expired. Please try again.',

    // Starter React: textos das telas de contas que o Livewire não tem.
    'projects' => [
        'archive' => 'Archive project',
        'unarchive' => 'Reactivate project',
    ],
    'profile_photo' => [
        'remove' => 'Remove photo',
        'remove_warning' => 'The photo leaves your profile and your initials take its place.',
        'removed' => 'Profile photo removed.',
    ],

];
