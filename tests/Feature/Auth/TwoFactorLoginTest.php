<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Auth\Enums\VerificationPurpose;

// Segundo fator por código de e-mail no login (regra do pacote): senha certa
// NÃO autentica; o código conclui. A tela do código é Inertia; os envios são
// do pacote.

beforeEach(function () {
    Mail::fake();
    config()->set('security.rate_limit.sensitive', 1000);
});

function twoFactorUser(): User
{
    return User::factory()->create(['password' => 'LoginForte123', 'two_factor_enabled_at' => now()]);
}

function passwordStep(User $user): TestResponse
{
    return test()->withHeaders(inertiaHeaders())->post('/login', ['email' => $user->email, 'password' => 'LoginForte123']);
}

it('senha certa com o segundo fator leva à tela do código, sem autenticar', function () {
    $user = twoFactorUser();

    passwordStep($user)->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();

    $this->flushHeaders()->get('/two-factor-challenge')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/two-factor-challenge')
        ->where('email', $user->email)
        ->has('codeTtlMinutes')
        ->where('auth.user', null));
});

it('código certo conclui o login com carga completa do painel', function () {
    $user = twoFactorUser();
    passwordStep($user);

    $this->withHeaders(inertiaHeaders())
        ->post('/two-factor-challenge', ['code' => lastVerificationCode(VerificationPurpose::LoginChallenge)])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('código errado fica na tela do código, com a mensagem no campo code', function () {
    $user = twoFactorUser();
    passwordStep($user);

    $code = lastVerificationCode(VerificationPurpose::LoginChallenge);

    $this->post('/two-factor-challenge', ['code' => $code === '000000' ? '000001' : '000000'])
        ->assertRedirect(route('two-factor.challenge'))
        ->assertSessionHasErrors(['code' => __('auth.two_factor.invalid')]);

    $this->assertGuest();
});

it('reenvia o código com o aviso e respeita o intervalo', function () {
    $user = twoFactorUser();
    passwordStep($user);

    $this->post('/two-factor-challenge/resend')
        ->assertRedirect(route('two-factor.challenge'))
        ->assertSessionHasErrors('code');

    $this->travel(2)->minutes();

    $this->post('/two-factor-challenge/resend')
        ->assertRedirect(route('two-factor.challenge'))
        ->assertSessionHas('status', __('auth.two_factor.resent'));
});

it('desistir volta ao login e mata o código', function () {
    $user = twoFactorUser();
    passwordStep($user);
    $code = lastVerificationCode(VerificationPurpose::LoginChallenge);

    $this->post('/two-factor-challenge/cancel')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('auth.two_factor.cancelled'));

    $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('login'));
    $this->assertGuest();
});

it('sem estado intermediário, a tela do código volta ao login', function () {
    $this->get('/two-factor-challenge')->assertRedirect(route('login'));
});

it('o código passa pelo throttle:sensitive do controller do pacote', function () {
    config()->set('security.rate_limit.sensitive', 2);
    $user = twoFactorUser();

    passwordStep($user);
    $this->post('/two-factor-challenge', ['code' => '000000']);
    $this->post('/two-factor-challenge', ['code' => '000000'])->assertStatus(429);
});
