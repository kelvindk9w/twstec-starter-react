<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Auth\Enums\UserStatus;

// Login do starter React: a tela é uma página Inertia; o envio é do
// controller do pacote twstec/kit-auth (limite de tentativas, conta ativa,
// anti-enumeração, sessão regenerada) — as MESMAS mensagens do Livewire.

it('mostra a tela de login como página Inertia', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
});

it('autentica e leva ao painel (carga normal: redirect)', function () {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('autentica numa visita do Inertia com carga completa do destino (409 + X-Inertia-Location)', function () {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->withHeaders(inertiaHeaders())
        ->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('recusa senha errada com a mensagem genérica, no campo email (anti-enumeração)', function () {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->from('/login')
        ->withHeaders(inertiaHeaders())
        ->post('/login', ['email' => $user->email, 'password' => 'SenhaErrada123'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('usa a mesma mensagem para e-mail inexistente', function () {
    $this->post('/login', ['email' => 'naoexiste@example.com', 'password' => 'Qualquer123'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
});

it('valida os campos com as mensagens do Laravel em pt-BR', function () {
    $this->post('/login', [])
        ->assertSessionHasErrors(['email', 'password']);

    expect(session('errors')->get('email')[0])->toContain('obrigatório');
});

it('as mensagens de erro chegam como prop `errors` na página seguinte', function () {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'Errada123']);

    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->component('auth/login')
        ->where('errors.email', __('auth.failed')));
});

it('bloqueia depois de N tentativas, mesmo com a senha certa', function () {
    config()->set('security.rate_limit.sensitive', 100);
    config()->set('auth.login.max_attempts', 3);

    $user = User::factory()->create(['password' => 'LoginForte123']);

    foreach (range(1, 3) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'SenhaErrada123'])
            ->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->get('email')[0])->toContain('Muitas tentativas');
    $this->assertGuest();
});

it('libera o login depois do bloqueio', function () {
    config()->set('security.rate_limit.sensitive', 100);
    config()->set('auth.login.max_attempts', 2);
    config()->set('auth.login.lockout_minutes', 15);

    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'Errada123']);
    $this->post('/login', ['email' => $user->email, 'password' => 'Errada123']);
    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123']);
    $this->assertGuest();

    $this->travel(16)->minutes();

    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('o envio do login passa pelo throttle:sensitive do controller do pacote (429)', function () {
    config()->set('security.rate_limit.sensitive', 3);
    config()->set('auth.login.max_attempts', 100);

    foreach (range(1, 3) as $i) {
        $this->post('/login', ['email' => "x{$i}@example.com", 'password' => 'Errada123'])
            ->assertStatus(302);
    }

    $this->post('/login', ['email' => 'x4@example.com', 'password' => 'Errada123'])
        ->assertStatus(429);
});

it('nega conta inativa mesmo com a senha certa', function () {
    $user = User::factory()->create(['password' => 'LoginForte123', 'status' => UserStatus::Blocked]);

    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])
        ->assertSessionHasErrors(['email' => __('auth.account_inactive')]);

    $this->assertGuest();
});

it('regenera o ID da sessão no login (fixation)', function () {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $this->get('/login');
    $antes = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123']);

    expect(session()->getId())->not->toBe($antes);
});

it('cookie de sessão HttpOnly e SameSite=Lax, com o nome próprio do starter', function () {
    $user = User::factory()->create(['password' => 'LoginForte123']);

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123']);

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and(mb_strtolower((string) $cookie->getSameSite()))->toBe('lax');
});

it('recusa o envio sem token CSRF (419)', function () {
    enforceCsrf();

    $this->post('/login', ['email' => 'a@example.com', 'password' => 'x'])->assertStatus(419);
});

it('aceita o envio do Inertia com o cabeçalho X-XSRF-TOKEN', function () {
    enforceCsrf();
    $user = User::factory()->create(['password' => 'LoginForte123']);

    // O cliente HTTP do Inertia lê o cookie XSRF-TOKEN e devolve o valor no
    // cabeçalho X-XSRF-TOKEN.
    $xsrf = collect($this->get('/login')->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN')
        ->getValue();

    $this->withHeaders([...inertiaHeaders(), 'X-XSRF-TOKEN' => $xsrf])
        ->post('/login', ['email' => $user->email, 'password' => 'LoginForte123'])
        ->assertStatus(409);

    $this->assertAuthenticatedAs($user);
});

it('uma visita do Inertia com a sessão vencida (419) volta à tela com o aviso traduzido', function () {
    enforceCsrf();

    $this->from('/login')
        ->withHeaders(inertiaHeaders())
        ->post('/login', ['email' => 'a@example.com', 'password' => 'x'])
        ->assertRedirect('/login')
        ->assertSessionHas('status', __('ui.session_expired'));
});

it('quem já está logado não vê a tela de login', function () {
    $this->actingAs(User::factory()->create())->get('/login')->assertRedirect(route('dashboard'));
});
