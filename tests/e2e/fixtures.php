<?php

declare(strict_types=1);

// =============================================================================
// Pessoas FIXAS do E2E do starter React, no banco de DESENVOLVIMENTO.
//
//   docker compose exec -T app php artisan tinker --execute="require 'tests/e2e/fixtures.php';"
//
// O React não tem a demonstração (twstec/kit-demo), então não há conta demo
// nem login pré-preenchido: o E2E usa pessoas próprias, criadas (ou
// devolvidas ao estado inicial) por este script — pode rodar quantas vezes
// quiser, e deve rodar de novo sempre que AUTH_TWO_FACTOR_REQUIRED mudar.
//
// - e2e@example.com: a pessoa comum do painel (sessão do global-setup).
//   E-mail confirmado, ativa, idioma pt_BR, tema do sistema, sem foto e com
//   senha de transação (as chaves de API pedem a confirmação de segurança).
// - admin-e2e@example.com: super admin do /admin (login do global-setup e a
//   limpeza das pessoas que os testes criam). NÃO começa com `e2e-`: esse é o
//   prefixo das pessoas que os testes criam e apagam.
// - login-e2e@example.com e admin-login-e2e@example.com: as mesmas duas
//   figuras, só para os testes de "login pela tela" (painel e /admin). Com o
//   segundo fator obrigatório, cada login manda um código, e um código novo
//   só sai depois do intervalo de reenvio — o global-setup acabou de logar as
//   duas de cima.
//
// SEGUNDO FATOR OBRIGATÓRIO (AUTH_TWO_FACTOR_REQUIRED=admins|all): quem a
// regra alcança (TwoFactorRequirement::appliesTo — no modo `admins`, só quem
// é administrador) nasce com o segundo fator LIGADO e com a senha de
// transação que ligar exige; o login dela passa pelo código por e-mail, que o
// E2E lê no Mailpit. Sem a regra, ninguém tem o segundo fator. Os códigos de
// verificação que as pessoas fixas tinham (de rodadas anteriores) saem:
// estado inicial, sem intervalo de reenvio pendente.
//
// As senhas podem vir do ambiente (E2E_USER_PASSWORD, E2E_ADMIN_PASSWORD),
// as mesmas que o Playwright lê.
// =============================================================================

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Twstec\Kit\Auth\Enums\UserStatus;
use Twstec\Kit\Auth\Models\VerificationCode;
use Twstec\Kit\Auth\Support\TwoFactorRequirement;

$transactionPassword = 'Trans4cao!Segura';

$fixture = function (string $email, string $name, string $password, bool $withTransactionPassword) use ($transactionPassword): User {
    $user = User::query()->where('email', $email)->first() ?? User::factory()->create([
        'email' => $email,
        'name' => $name,
    ]);

    $user->forceFill([
        'name' => $name,
        'password' => Hash::make($password),
        'email_verified_at' => $user->email_verified_at ?? now(),
        'status' => UserStatus::Active,
        'locale' => 'pt_BR',
        'theme' => null,
        'two_factor_enabled_at' => null,
        'transaction_password' => $withTransactionPassword ? Hash::make($transactionPassword) : null,
        'transaction_password_set_at' => $withTransactionPassword ? now() : null,
    ]);

    // Sem foto (a coluna existe com o pacote de uploads).
    if (Schema::hasColumn('users', 'avatar_upload_id')) {
        $user->forceFill(['avatar_upload_id' => null]);
    }

    $user->save();

    VerificationCode::query()->where('user_id', $user->id)->delete();

    return $user;
};

$userPassword = (string) (getenv('E2E_USER_PASSWORD') ?: 'E2eSenhaForte123');
$adminPassword = (string) (getenv('E2E_ADMIN_PASSWORD') ?: 'E2eAdminSenha123');

$people = [
    $fixture('e2e@example.com', 'Pessoa E2E', $userPassword, true),
    $fixture('login-e2e@example.com', 'Pessoa Login E2E', $userPassword, false),
    $fixture('admin-e2e@example.com', 'Admin E2E', $adminPassword, false),
    $fixture('admin-login-e2e@example.com', 'Admin Login E2E', $adminPassword, false),
];

// O /admin é opcional (twstec/kit-admin): sem ele, os admins do E2E são só
// pessoas comuns, sem uso. Viram admin ANTES de a regra ser conferida (no
// modo `admins`, quem é admin decide).
if (array_key_exists('user:make-admin', Artisan::all())) {
    foreach (['admin-e2e@example.com', 'admin-login-e2e@example.com'] as $email) {
        Artisan::call('user:make-admin', ['email' => $email]);
    }
}

$requirement = app(TwoFactorRequirement::class);
$withTwoFactor = [];

foreach ($people as $person) {
    $person->refresh();

    if (! $requirement->appliesTo($person)) {
        continue;
    }

    $person->forceFill([
        'two_factor_enabled_at' => now(),
        'transaction_password' => $person->transaction_password ?? Hash::make($transactionPassword),
        'transaction_password_set_at' => $person->transaction_password_set_at ?? now(),
    ])->save();

    $withTwoFactor[] = $person->email;
}

echo 'Pessoas fixas do E2E prontas: e2e@, login-e2e@, admin-e2e@ e admin-login-e2e@example.com'
    .' (AUTH_TWO_FACTOR_REQUIRED='.TwoFactorRequirement::mode().'; segundo fator ligado: '.($withTwoFactor === [] ? 'ninguém' : implode(', ', $withTwoFactor)).')'
    .PHP_EOL;
