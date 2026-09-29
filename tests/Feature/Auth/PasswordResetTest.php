<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Auth\Notifications\ResetPasswordNotification;

// Esqueci/redefinir senha: telas Inertia, envio pelo pacote, resposta
// uniforme (anti-enumeração).

beforeEach(fn () => Notification::fake());

it('mostra a tela de esqueci a senha', function () {
    $this->get('/forgot-password')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/forgot-password'));
});

it('responde igual exista ou não o e-mail (anti-enumeração)', function () {
    $user = User::factory()->create();

    $existente = $this->withHeaders(inertiaHeaders())->post('/forgot-password', ['email' => $user->email]);
    $inexistente = $this->withHeaders(inertiaHeaders())->post('/forgot-password', ['email' => 'ninguem@example.com']);

    foreach ([$existente, $inexistente] as $response) {
        $response->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('passwords.sent'));
    }

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('a tela de redefinição recebe o token do link e o e-mail', function () {
    $this->get('/reset-password/abc123?email=ana@example.com')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/reset-password')
            ->where('token', 'abc123')
            ->where('email', 'ana@example.com'));
});

it('redefine a senha e leva ao login com o aviso', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ])->assertRedirect(route('login'))->assertSessionHas('status', __('passwords.reset'));

    expect(Hash::check('NovaSenha123', $user->fresh()->password))->toBeTrue();
});

it('token inválido volta ao formulário com o motivo no campo email', function () {
    $user = User::factory()->create();

    $this->from('/reset-password/errado')->post('/reset-password', [
        'token' => 'errado',
        'email' => $user->email,
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ])->assertRedirect('/reset-password/errado')
        ->assertSessionHasErrors(['email' => __('passwords.token')]);
});
