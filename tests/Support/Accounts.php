<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Account\Services\AccountService;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\ApiKeys\Models\ApiKey;
use Twstec\Kit\Accounts\ApiKeys\Services\ApiKeyService;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;

// =============================================================================
// Arranjo dos testes das telas de contas do React (o mesmo do starter
// Livewire): conta pessoal, conta de empresa com equipe, chave e projeto numa
// conta, leitura sem filtro de conta para as conferências do teste, e a
// confirmação sensível pelos envios HTTP do painel.
// =============================================================================

/** A senha de transação do UserFactory::withTransactionPassword(). */
const SENHA_TRANSACAO = 'Trans4cao!Segura';

function contaPessoal(User $user): Account
{
    return app(AccountService::class)->personalAccountOf($user)
        ?? throw new LogicException('Pessoa sem conta pessoal.');
}

/**
 * Leitura/gravação direta do teste SEM filtro de conta (o escopo das contas
 * lança exceção sem conta atual) — só para arranjo e conferência.
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function comoSistema(Closure $callback): mixed
{
    return Accounts::asSystem('teste', $callback);
}

/**
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function naConta(Account $conta, User $user, Closure $callback): mixed
{
    return Accounts::actingAs($conta, $callback, $user);
}

function projetoNa(Account $conta, User $user, string $nome): Project
{
    return naConta($conta, $user, fn (): Project => Project::createWithPublicCodeRetry(['name' => $nome, 'created_by' => $user->id]));
}

/**
 * @return array{api_key: ApiKey, secret_key: string}
 */
function chaveNa(Account $conta, User $user, array $data = []): array
{
    return naConta($conta, $user, fn (): array => app(ApiKeyService::class)->create($user, ['name' => 'Chave de teste', ...$data]));
}

/**
 * Conta de empresa com dono, admin e member.
 *
 * @return array{empresa: Account, dono: User, admin: User, membro: User}
 */
function contaComEquipe(): array
{
    $dono = User::factory()->withTransactionPassword()->create(['name' => 'Dona Equipe']);
    $admin = User::factory()->withTransactionPassword()->create(['name' => 'Admin Equipe']);
    $membro = User::factory()->withTransactionPassword()->create(['name' => 'Membro Equipe']);
    $empresa = app(AccountService::class)->createAccount('Equipe SA', $dono);
    app(AccountService::class)->addMember($empresa, $admin, AccountRole::Admin);
    app(AccountService::class)->addMember($empresa, $membro, AccountRole::Member);

    return ['empresa' => $empresa, 'dono' => $dono, 'admin' => $admin, 'membro' => $membro];
}

/**
 * Entra como a pessoa, já na conta escolhida (a seleção do seletor).
 */
function entrarNa(User $user, Account $conta): void
{
    test()->actingAs($user)->withSession(['accounts.current' => $conta->uuid]);
}

/**
 * O último código de ação sensível "enviado" (Mail::fake).
 */
function ultimoCodigo(): string
{
    /** @var VerificationCodeMail $mail */
    $mail = Mail::queued(VerificationCodeMail::class)->last();

    return $mail->code;
}

/**
 * Confirmação sensível pelos envios do painel React: `…/code` com a senha
 * (stage=send) e a ação com o código do e-mail.
 *
 * @param  array<string, mixed>  $dados
 */
function confirmarSensivel(string $codeUrl, string $method, string $url, array $dados = [], string $senha = SENHA_TRANSACAO): TestResponse
{
    test()->withHeaders(inertiaHeaders())
        ->post($codeUrl, [...$dados, 'stage' => 'send', 'transaction_password' => $senha])
        ->assertSessionHasNoErrors();

    return test()->withHeaders(inertiaHeaders())->{$method}($url, [...$dados, 'code' => ultimoCodigo()]);
}
