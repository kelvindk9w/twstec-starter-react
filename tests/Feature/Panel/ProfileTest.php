<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Auth\PasswordPolicy;

// Perfil: os mesmos blocos, regras e mensagens do starter Livewire.

it('mostra o perfil com o estado do segundo fator e a dica da senha', function () {
    $this->actingAs(User::factory()->create())->get('/profile')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('passwordHint', PasswordPolicy::hint())
            ->where('twoFactor.available', true)
            ->where('twoFactor.enabled', false)
            ->where('twoFactor.blockedReason', fn ($reason) => is_string($reason) && $reason !== ''));
});

it('atualiza nome e idioma, com o aviso no idioma novo', function () {
    $user = User::factory()->create(['name' => 'Antes', 'locale' => 'pt_BR']);

    $this->actingAs($user)->from('/profile')
        ->patch('/profile', ['name' => 'Depois', 'locale' => 'en'])
        ->assertRedirect('/profile')
        ->assertSessionHas('status', trans('panel.common.saved', [], 'en'));

    expect($user->fresh())->name->toBe('Depois')->locale->toBe('en');
});

it('recusa idioma fora da lista, com a mensagem do Livewire', function () {
    $this->actingAs(User::factory()->create())->from('/profile')
        ->patch('/profile', ['name' => 'X', 'locale' => 'xx'])
        ->assertSessionHasErrors(['locale' => __('validation.in', ['attribute' => __('panel.profile.locale_label')])]);
});

it('o e-mail não muda pelo perfil', function () {
    $user = User::factory()->create(['email' => 'fixo@example.com']);

    $this->actingAs($user)->patch('/profile', ['name' => 'X', 'locale' => 'pt_BR', 'email' => 'outro@example.com']);

    expect($user->fresh()->email)->toBe('fixo@example.com');
});

it('troca a senha de login exigindo a atual', function () {
    $user = User::factory()->create(['password' => 'SenhaAtual123']);

    $this->actingAs($user)->from('/profile')->put('/profile/password', [
        'current_password' => 'Errada123',
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ])->assertSessionHasErrors(['current_password' => __('panel.profile.current_password_invalid')]);

    $this->from('/profile')->put('/profile/password', [
        'current_password' => 'SenhaAtual123',
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ])->assertSessionHasNoErrors()->assertSessionHas('status', __('panel.profile.password_updated'));

    expect(Hash::check('NovaSenha123', $user->fresh()->password))->toBeTrue();
});

it('a confirmação diferente usa os rótulos do Livewire na mensagem', function () {
    $this->actingAs(User::factory()->create(['password' => 'SenhaAtual123']))->from('/profile')
        ->put('/profile/password', [
            'current_password' => 'SenhaAtual123',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'Outra123',
        ])->assertSessionHasErrors(['password_confirmation' => __('validation.same', [
            'attribute' => __('auth.ui.password_confirmation'),
            'other' => __('auth.ui.new_password'),
        ])]);
});

it('a senha de transação do perfil vai para o controller do pacote (mesma regra)', function () {
    $user = User::factory()->create(['password' => 'SenhaLogin123']);

    $this->actingAs($user)->from('/profile')->put('/settings/transaction-password', [
        'transaction_password' => 'SenhaLogin123',
        'transaction_password_confirmation' => 'SenhaLogin123',
    ])->assertSessionHasErrors('transaction_password');

    $this->from('/profile')->put('/settings/transaction-password', [
        'transaction_password' => 'Transacao123',
        'transaction_password_confirmation' => 'Transacao123',
    ])->assertRedirect('/profile')->assertSessionHas('status', __('auth.transaction_password.saved'));

    expect($user->fresh()->hasTransactionPassword())->toBeTrue();

    // Na troca, a atual é exigida.
    $this->from('/profile')->put('/settings/transaction-password', [
        'transaction_password' => 'OutraTransacao1',
        'transaction_password_confirmation' => 'OutraTransacao1',
    ])->assertSessionHasErrors('current_transaction_password');
});

it('a tela própria da senha de transação é uma página Inertia', function () {
    $this->actingAs(User::factory()->create())->get('/settings/transaction-password')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/transaction-password'));
});

it('o tema é gravado na conta (JSON e visita do Inertia)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/settings/theme', ['theme' => 'dark'])->assertOk()->assertJson(['theme' => 'dark']);
    expect($user->fresh()->theme)->toBe('dark');

    $this->from('/profile')->withHeaders(inertiaHeaders())->post('/settings/theme', ['theme' => 'light'])->assertRedirect('/profile');
    expect($user->fresh()->theme)->toBe('light');

    $this->postJson('/settings/theme', ['theme' => 'roxo'])->assertStatus(422);
});

it('o documento raiz leva o tema da conta para o script do <head>', function () {
    $this->actingAs(User::factory()->create(['theme' => 'dark']))->get('/profile')
        ->assertSee('data-theme-default="dark"', false);
});
