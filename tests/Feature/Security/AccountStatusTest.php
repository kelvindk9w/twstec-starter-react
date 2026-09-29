<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Auth\Enums\UserStatus;

// O status da conta vale a cada requisição (EnsureAccountIsActive, ligado
// pelo pacote twstec/kit-auth no fim do grupo `web`) — também nas visitas do
// Inertia. Nada no starter React liga essa proteção: ela vem do pacote.

it('conta desativada com sessão aberta perde o painel na próxima requisição', function (string $url, UserStatus $status) {
    $user = User::factory()->create();

    $this->actingAs($user)->get($url)->assertOk();
    $user->forceFill(['status' => $status])->save();

    $this->get($url)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.account_inactive')]);

    $this->assertGuest();
})->with([
    'painel' => '/dashboard',
    'perfil' => '/profile',
    'notificações' => '/notifications',
    'senha de transação' => '/settings/transaction-password',
])->with([
    'bloqueada' => UserStatus::Blocked,
    'pendente' => UserStatus::Pending,
]);

it('numa visita do Inertia, a recusa vira a página de login com a mensagem no campo email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $user->forceFill(['status' => UserStatus::Blocked])->save();

    $this->withHeaders(inertiaHeaders())->get('/profile')->assertRedirect(route('login'));

    // O cliente do Inertia segue o redirect (XHR) e recebe a página de login
    // com o erro compartilhado.
    $this->withHeaders(inertiaHeaders())->get(route('login'))
        ->assertOk()
        ->assertJsonPath('component', 'auth/login')
        ->assertJsonPath('props.errors.email', __('auth.account_inactive'));

    $this->assertGuest();
});

it('um envio de formulário do painel também é recusado', function () {
    $user = User::factory()->create(['name' => 'Antes']);

    $this->actingAs($user)->get('/profile')->assertOk();
    $user->forceFill(['status' => UserStatus::Blocked])->save();

    $this->withHeaders(inertiaHeaders())->patch('/profile', ['name' => 'Depois', 'locale' => 'pt_BR'])
        ->assertRedirect(route('login'));

    expect($user->fresh()->name)->toBe('Antes');
    $this->assertGuest();
});

it('a mensagem sai no idioma da conta', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $user->forceFill(['status' => UserStatus::Blocked])->save();

    $this->get('/dashboard')->assertSessionHasErrors(['email' => trans('auth.account_inactive', [], 'en')]);
});

it('a página seguinte (login) mostra a recusa', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard');
    $user->forceFill(['status' => UserStatus::Blocked])->save();
    $this->get('/dashboard');

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('errors.email', __('auth.account_inactive')));
});
