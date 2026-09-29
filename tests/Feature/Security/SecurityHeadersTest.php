<?php

declare(strict_types=1);

use App\Models\User;

// Cabeçalhos e CSP: a MESMA pilha do starter Livewire (instalada pelo pacote
// twstec/kit-foundation). As páginas React rodam com a CSP estrita — sem
// 'unsafe-eval'; só o /admin (Filament) a ganha.

it('as páginas React carregam os cabeçalhos de segurança', function (string $url) {
    $response = $this->get($url);

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy')
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('X-Correlation-Id');
})->with(['/', '/login', '/register', '/forgot-password']);

it('CSP estrita nas páginas React: sem unsafe-eval, scripts só da própria origem', function () {
    $user = User::factory()->create();

    foreach (['/login', '/dashboard', '/profile'] as $url) {
        $csp = (string) ($url === '/login' ? $this->get($url) : $this->actingAs($user)->get($url))
            ->headers->get('Content-Security-Policy');

        expect($csp)->not->toContain('unsafe-eval')
            ->and($csp)->toContain("script-src 'self'")
            ->and($csp)->toContain("connect-src 'self'")
            ->and($csp)->toContain("frame-ancestors 'none'")
            ->and($csp)->toContain("form-action 'self'");
    }
});

it('a visita do Inertia (JSON) também leva a CSP e o X-Frame-Options', function () {
    $this->withHeaders(inertiaHeaders())->get('/login')
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy');
});

it('os dados da página vão num <script type="application/json"> (não executa), não em atributo', function () {
    $html = (string) $this->get('/login')->getContent();

    expect($html)->toContain('<script data-page="app" type="application/json">')
        ->and($html)->not->toContain(' data-page="{');
});

it('só o /admin ganha unsafe-eval (Filament, decisão documentada)', function () {
    expect((string) $this->get('/admin/login')->headers->get('Content-Security-Policy'))->toContain('unsafe-eval');
})->group('admin');

it('não envia HSTS fora de HTTPS/produção', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
});
