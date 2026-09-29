<?php

declare(strict_types=1);

use App\Models\User;

// Logout: sessão invalidada, CSRF renovado e carga completa do login (nada da
// sessão anterior fica na memória do front).

it('encerra a sessão e leva ao login com o aviso (carga completa no Inertia)', function () {
    $this->actingAs(User::factory()->create());

    $this->withHeaders(inertiaHeaders())->post('/logout')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));

    $this->assertGuest();
    expect(session('status'))->toBe(__('auth.logged_out'));
});

it('fora do Inertia, redireciona ao login', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('exige sessão e token CSRF', function () {
    $this->post('/logout')->assertRedirect(route('login'));

    enforceCsrf();
    $this->actingAs(User::factory()->create())->post('/logout')->assertStatus(419);
});
