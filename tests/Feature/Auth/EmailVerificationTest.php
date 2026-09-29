<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Auth\Support\EmailVerification;

// Verificação de e-mail obrigatória (regra do pacote): painel fechado até
// confirmar; tela de aviso Inertia; link assinado; reenvio com intervalo.

beforeEach(fn () => Notification::fake());

function unverifiedUser(): User
{
    return User::factory()->create(['email_verified_at' => null]);
}

it('conta sem e-mail confirmado não entra no painel', function (string $url) {
    $this->actingAs(unverifiedUser())->get($url)->assertRedirect(route('verification.notice'));
})->with(['/dashboard', '/profile', '/notifications', '/settings/transaction-password']);

it('a tela de aviso é uma página Inertia com o e-mail', function () {
    $user = unverifiedUser();

    $this->actingAs($user)->get('/email/verify')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/verify-email')
        ->where('email', $user->email)
        ->where('navigation', []));
});

it('com o e-mail já confirmado, o aviso devolve ao painel', function () {
    $this->actingAs(User::factory()->create())->get('/email/verify')->assertRedirect(route('dashboard'));
});

it('o link do e-mail confirma e leva ao painel com o aviso', function () {
    $user = unverifiedUser();

    $this->actingAs($user)
        ->get(EmailVerification::verificationUrl($user))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and(session('status'))->toBe(__('auth.email_verification.verified'));
});

it('depois de confirmar, volta à página tentada — só dentro da aplicação (SafeRedirect)', function () {
    $user = unverifiedUser();

    $this->actingAs($user)->withSession(['url.intended' => 'https://evil.example/phish'])
        ->get(EmailVerification::verificationUrl($user))
        ->assertRedirect(route('dashboard'));

    $other = unverifiedUser();

    $this->actingAs($other)->withSession(['url.intended' => url('/notifications')])
        ->get(EmailVerification::verificationUrl($other))
        ->assertRedirect(url('/notifications'));
});

it('link adulterado volta ao aviso com a explicação fixa (verification_error)', function () {
    $user = unverifiedUser();

    $this->actingAs($user)
        ->get(EmailVerification::verificationUrl($user).'x')
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('verification_error', __('auth.email_verification.invalid_link'));

    $this->get('/email/verify')->assertInertia(fn (Assert $page) => $page
        ->where('flash.verification_error', __('auth.email_verification.invalid_link')));
});

it('reenvia o link e respeita o intervalo mínimo', function () {
    $user = unverifiedUser();

    $this->actingAs($user)->post('/email/verification-notification')
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', __('auth.email_verification.sent'));

    $this->post('/email/verification-notification')
        ->assertSessionHas('verification_error');
});
