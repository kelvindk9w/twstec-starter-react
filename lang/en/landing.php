<?php

declare(strict_types=1);

// Product SITE keys (header, footer and home page): used by the home page
// (resources/js/pages/welcome.tsx). The group is called `landing` for
// historical reasons, and the name stayed so overrides keep working; the texts
// are the same as the Livewire starter's.

return [

    'nav' => [
        'login' => 'Sign in',
        'register' => 'Create account',
    ],

    'footer' => [
        'tagline' => 'Sign in or create your account to get started.',
        'links_heading' => 'Shortcuts',
        'api_status' => 'API status',
        'rights' => '© :year :company — All rights reserved',
        'developed_by' => 'Developed by',
    ],

];
