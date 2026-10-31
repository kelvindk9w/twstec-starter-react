<?php

declare(strict_types=1);

// Claves del SITIO del producto (cabecera, pie y página inicial): las usa la
// página inicial (resources/js/pages/welcome.tsx). El grupo se llama `landing`
// por historia, y el nombre se quedó para no romper a quien ya sobrescribió
// estas claves; los textos son los mismos del starter Livewire.

return [

    'nav' => [
        'login' => 'Entrar',
        'register' => 'Crear cuenta',
    ],

    'footer' => [
        'tagline' => 'Inicia sesión o crea tu cuenta para empezar.',
        'links_heading' => 'Atajos',
        'api_status' => 'Estado de la API',
        'rights' => '© :year :company — Todos los derechos reservados',
        'developed_by' => 'Desarrollado por',
    ],

];
