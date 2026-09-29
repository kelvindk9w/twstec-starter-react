<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Auth\Notifications\VerifyEmailNotification;
use Twstec\Kit\Auth\PasswordPolicy;

// Cadastro: página Inertia + envio pelo controller do pacote. Com a
// verificação de e-mail ligada, a conta nova vai à tela de aviso.

beforeEach(fn () => Notification::fake());

it('mostra a tela de cadastro com a dica da política de senha', function () {
    $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/register')
        ->where('passwordHint', ucfirst(PasswordPolicy::hint())));
});

it('cria a conta, autentica e leva à tela de aviso (carga completa no Inertia)', function () {
    $this->withHeaders(inertiaHeaders())->post('/register', [
        'name' => 'Maria Teste',
        'email' => 'maria@example.com',
        'password' => 'SenhaForte123',
        'password_confirmation' => 'SenhaForte123',
    ])->assertStatus(409)->assertHeader('X-Inertia-Location', route('verification.notice'));

    $user = User::query()->where('email', 'maria@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
    expect(session('status'))->toBe(__('auth.email_verification.registered'));
});

it('sem a verificação de e-mail, vai direto ao painel', function () {
    config()->set('auth.email_verification.required', false);

    $this->post('/register', [
        'name' => 'João',
        'email' => 'joao@example.com',
        'password' => 'SenhaForte123',
        'password_confirmation' => 'SenhaForte123',
    ])->assertRedirect(route('dashboard'));

    expect(session('status'))->toBe(__('auth.registered'));
});

it('recusa e-mail já cadastrado com a mensagem de validação', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    $this->post('/register', [
        'name' => 'Dup',
        'email' => 'dup@example.com',
        'password' => 'SenhaForte123',
        'password_confirmation' => 'SenhaForte123',
    ])->assertSessionHasErrors('email');

    expect(session('errors')->get('email')[0])->toBe(__('validation.unique', ['attribute' => __('validation.attributes.email')]));
});

it('aplica a política de senha do pacote e a confirmação', function () {
    config()->set('auth.password_rules.min', 12);

    $this->post('/register', [
        'name' => 'Curta',
        'email' => 'curta@example.com',
        'password' => 'curta1',
        'password_confirmation' => 'diferente',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});
