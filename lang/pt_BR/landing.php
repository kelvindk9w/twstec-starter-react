<?php

declare(strict_types=1);

// Chaves do SITE do produto (cabeçalho, rodapé e página inicial): usadas pela
// página inicial (resources/js/pages/welcome.tsx). O grupo se chama `landing`
// por história — nasceu com a landing do kit — e o nome ficou para não quebrar
// quem já sobrescreveu estas chaves; os textos são os mesmos do starter
// Livewire.

return [

    'nav' => [
        'login' => 'Entrar',
        'register' => 'Criar conta',
    ],

    'footer' => [
        'tagline' => 'Entre ou crie a sua conta para começar.',
        'links_heading' => 'Atalhos',
        'api_status' => 'Status da API',
        'rights' => '© :year :company — Todos os direitos reservados',
        'developed_by' => 'Desenvolvido por',
    ],

];
