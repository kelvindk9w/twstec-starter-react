<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Twstec\Kit\Auth\Enums\VerificationPurpose;

// Ligar/desligar o segundo fator: ação sensível (senha de transação →
// código por e-mail → token de uso único, emitido e consumido no servidor).

beforeEach(function () {
    Mail::fake();
    config()->set('security.rate_limit.sensitive', 1000);
});

it('sem senha de transação, recusa com o motivo', function () {
    $this->actingAs(User::factory()->create())->from('/profile')
        ->post('/profile/two-factor/code', ['transaction_password' => 'qualquer'])
        ->assertSessionHasErrors('two_factor');

    Mail::assertNothingQueued();
});

it('senha de transação errada não envia código', function () {
    $this->actingAs(User::factory()->create(['transaction_password' => 'Transacao123']))->from('/profile')
        ->post('/profile/two-factor/code', ['transaction_password' => 'Errada123'])
        ->assertSessionHasErrors('transaction_password');

    Mail::assertNothingQueued();
});

it('liga e desliga com senha de transação + código', function () {
    $user = User::factory()->create(['transaction_password' => 'Transacao123']);

    $this->actingAs($user)->from('/profile')
        ->post('/profile/two-factor/code', ['transaction_password' => 'Transacao123'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('auth.verification_code.sent'));

    $this->from('/profile')->put('/profile/two-factor', [
        'code' => lastVerificationCode(VerificationPurpose::SensitiveAction),
        'enabled' => true,
    ])->assertSessionHasNoErrors()->assertSessionHas('status', __('auth.two_factor.enabled'));

    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull();

    $this->travel(2)->minutes();

    $this->from('/profile')->post('/profile/two-factor/code', ['transaction_password' => 'Transacao123']);
    $this->from('/profile')->put('/profile/two-factor', [
        'code' => lastVerificationCode(VerificationPurpose::SensitiveAction),
        'enabled' => false,
    ])->assertSessionHas('status', __('auth.two_factor.disabled'));

    expect($user->fresh()->two_factor_enabled_at)->toBeNull();
});

it('código errado não muda nada', function () {
    $user = User::factory()->create(['transaction_password' => 'Transacao123']);

    $this->actingAs($user)->from('/profile')->post('/profile/two-factor/code', ['transaction_password' => 'Transacao123']);
    $code = lastVerificationCode(VerificationPurpose::SensitiveAction);

    $this->from('/profile')->put('/profile/two-factor', [
        'code' => $code === '000000' ? '000001' : '000000',
        'enabled' => true,
    ])->assertSessionHasErrors('code');

    expect($user->fresh()->two_factor_enabled_at)->toBeNull();
});

it('código do LOGIN não serve para a ação sensível (finalidades separadas)', function () {
    $user = User::factory()->create(['password' => 'LoginForte123', 'transaction_password' => 'Transacao123', 'two_factor_enabled_at' => now()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'LoginForte123']);
    $loginCode = lastVerificationCode(VerificationPurpose::LoginChallenge);
    $this->post('/two-factor-challenge', ['code' => $loginCode]);
    $this->assertAuthenticatedAs($user);

    $this->from('/profile')->put('/profile/two-factor', ['code' => $loginCode, 'enabled' => false])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->two_factor_enabled_at)->not->toBeNull();
});

it('os dois envios passam pelo throttle:sensitive', function () {
    config()->set('security.rate_limit.sensitive', 2);
    $this->actingAs(User::factory()->create(['transaction_password' => 'Transacao123']));

    $this->post('/profile/two-factor/code', ['transaction_password' => 'x']);
    $this->post('/profile/two-factor/code', ['transaction_password' => 'x']);
    $this->post('/profile/two-factor/code', ['transaction_password' => 'x'])->assertStatus(429);
});

it('a troca de senha de login também tem limite (adivinhação da senha atual)', function () {
    config()->set('security.rate_limit.sensitive', 2);
    $this->actingAs(User::factory()->create());

    foreach (range(1, 2) as $i) {
        $this->put('/profile/password', ['current_password' => 'x', 'password' => 'Nova12345', 'password_confirmation' => 'Nova12345']);
    }

    $this->put('/profile/password', ['current_password' => 'x', 'password' => 'Nova12345', 'password_confirmation' => 'Nova12345'])
        ->assertStatus(429);
});
