<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Accounts\ApiKeys\Enums\ApiKeyStatus;
use Twstec\Kit\Accounts\ApiKeys\Models\ApiKey;
use Twstec\Kit\Accounts\ApiKeys\Support\ApiKeyHasher;

// =============================================================================
// Chaves de API pelo painel React: o caminho HTTP/Inertia do starter sobre o
// ApiKeyService e o SensitiveActionService (a regra, coberta pelo pacote e
// pelo Livewire). Aqui: papel conferido em CADA envio, conta alheia = 404, a
// confirmação sensível de verdade (senha → código do e-mail → token consumido
// no servidor) e a SECRETA exibida UMA vez — só na resposta imediata, fora das
// props, nunca na listagem, nunca na sessão.
// =============================================================================

beforeEach(function () {
    Mail::fake();
    config()->set('auth.verification.resend_cooldown_seconds', 0);
    config()->set('security.rate_limit.sensitive', 1000);
});

/**
 * @return array<string, mixed>
 */
function dadosDaChave(array $extra = []): array
{
    return ['name' => 'Integração do checkout', 'all_scopes' => true, 'scopes' => [], 'project_uuids' => [], 'expires_at' => null, ...$extra];
}

it('exige sessão e e-mail confirmado', function () {
    $this->get('/api-keys')->assertRedirect(route('login'));
})->group('accounts');

it('lista só as chaves da conta atual, sem a secreta nem o hash', function () {
    ['empresa' => $empresa, 'dono' => $dono] = contaComEquipe();
    $outra = User::factory()->create();

    ['api_key' => $minha, 'secret_key' => $secreta] = chaveNa($empresa, $dono, ['name' => 'Da empresa']);
    ['api_key' => $alheia] = chaveNa(contaPessoal($outra), $outra, ['name' => 'Alheia']);

    entrarNa($dono, $empresa);

    $response = $this->withHeaders(inertiaHeaders())->get('/api-keys')->assertOk();

    $response->assertJsonPath('component', 'api-keys/index')
        ->assertJsonPath('props.keys.0.uuid', $minha->uuid)
        ->assertJsonPath('props.keys.0.publicKey', $minha->public_key)
        ->assertJsonPath('props.canManageKeys', true)
        ->assertJsonCount(1, 'props.keys');

    $conteudo = (string) $response->getContent();
    $hash = comoSistema(fn () => ApiKey::query()->whereKey($minha->id)->value('secret_hash'));

    expect($conteudo)->not->toContain($secreta)
        ->and($conteudo)->not->toContain((string) $hash)
        ->and($conteudo)->not->toContain($alheia->uuid)
        ->and($response->json('flash'))->toBeEmpty();
})->group('accounts');

it('member vê as chaves mas não gere: sem botões e 403 em cada envio', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'membro' => $membro] = contaComEquipe();
    ['api_key' => $chave] = chaveNa($empresa, $dono);

    entrarNa($membro, $empresa);

    $this->withHeaders(inertiaHeaders())->get('/api-keys')->assertOk()
        ->assertJsonPath('props.canManageKeys', false)
        ->assertJsonCount(1, 'props.keys');

    $this->post('/api-keys/code', dadosDaChave(['stage' => 'check']))->assertForbidden();
    $this->post('/api-keys', dadosDaChave(['code' => '123456']))->assertForbidden();
    $this->post("/api-keys/{$chave->uuid}/rotate/code", ['grace_minutes' => 0, 'stage' => 'check'])->assertForbidden();
    $this->post("/api-keys/{$chave->uuid}/rotate", ['grace_minutes' => 0, 'code' => '123456'])->assertForbidden();
    $this->delete("/api-keys/{$chave->uuid}")->assertForbidden();
    $this->put("/api-keys/{$chave->uuid}/projects", ['project_uuids' => []])->assertForbidden();

    expect(comoSistema(fn () => ApiKey::query()->count()))->toBe(1)
        ->and(comoSistema(fn () => $chave->fresh()->status))->toBe(ApiKeyStatus::Active);
    Mail::assertNothingQueued();
})->group('accounts');

it('cria pela tela com a confirmação sensível e mostra a secreta UMA vez — só na resposta imediata', function () {
    ['empresa' => $empresa, 'admin' => $admin] = contaComEquipe();
    entrarNa($admin, $empresa);

    // Passo 1: a conferência do pedido não manda código.
    $this->withHeaders(inertiaHeaders())->post('/api-keys/code', dadosDaChave(['stage' => 'check']))
        ->assertRedirect('/api-keys')->assertSessionHasNoErrors();
    Mail::assertNothingQueued();

    // Passos 2 e 3: senha → código → a chave.
    $response = confirmarSensivel('/api-keys/code', 'post', '/api-keys', dadosDaChave())->assertOk();

    $chave = comoSistema(fn () => ApiKey::query()->sole());
    $secreta = $response->json('flash.revealedKey.secret');

    expect($chave->account_id)->toBe($empresa->id)
        ->and($chave->created_by)->toBe($admin->id)
        ->and($secreta)->toBeString()->toStartWith('sk_')
        ->and(app(ApiKeyHasher::class)->verify($secreta, $chave->secret_hash))->toBeTrue()
        ->and($response->json('flash.revealedKey.publicKey'))->toBe($chave->public_key)
        // O endereço da resposta é o da lista (recarregar não repete nada).
        ->and($response->json('url'))->toBe('/api-keys')
        // A secreta está FORA das props (o Inertia não guarda `flash` no histórico).
        ->and(json_encode($response->json('props')))->not->toContain($secreta)
        // Nem na sessão depois da resposta.
        ->and(json_encode(session()->all()))->not->toContain($secreta);

    // Nunca mais: a listagem (visita e primeira carga) não a traz de volta.
    $depois = $this->withHeaders(inertiaHeaders())->get('/api-keys')->assertOk();
    $html = $this->get('/api-keys')->assertOk();

    expect((string) $depois->getContent())->not->toContain($secreta)
        ->and($depois->json('flash'))->toBeEmpty()
        ->and((string) $html->getContent())->not->toContain($secreta)
        ->and($depois->json('props.keys.0.publicKey'))->toBe($chave->public_key);
})->group('accounts');

it('escopos granulares: com "todas" desligado, exige a seleção e grava só os marcados', function () {
    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));

    $this->post('/api-keys/code', dadosDaChave(['all_scopes' => false, 'stage' => 'check']))
        ->assertSessionHasErrors('scopes');

    confirmarSensivel('/api-keys/code', 'post', '/api-keys', dadosDaChave(['all_scopes' => false, 'scopes' => ['projects:read']]))->assertOk();

    expect(comoSistema(fn () => ApiKey::query()->sole()->scopes))->toBe(['projects:read']);
})->group('accounts');

it('sem senha de transação definida, a tela avisa e nada é enviado', function () {
    $dono = User::factory()->create();
    entrarNa($dono, contaPessoal($dono));

    $this->post('/api-keys/code', dadosDaChave(['stage' => 'check']))
        ->assertSessionHasErrors(['name' => __('panel.api_keys.sensitive_requires_password')]);

    Mail::assertNothingQueued();
})->group('accounts');

it('senha de transação errada e código errado: nenhuma chave', function () {
    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));

    $this->post('/api-keys/code', dadosDaChave(['stage' => 'send', 'transaction_password' => 'errada-123']))
        ->assertSessionHasErrors('transaction_password');

    $this->post('/api-keys/code', dadosDaChave(['stage' => 'send', 'transaction_password' => SENHA_TRANSACAO]))->assertSessionHasNoErrors();
    $codigo = ultimoCodigo();
    $errado = $codigo === '000000' ? '111111' : '000000';

    $this->post('/api-keys', dadosDaChave(['code' => $errado]))->assertSessionHasErrors('code');

    expect(comoSistema(fn () => ApiKey::query()->count()))->toBe(0);
})->group('accounts');

it('rotaciona com período de transição: a nova herda tudo, a antiga segue válida até o fim', function () {
    ['empresa' => $empresa, 'dono' => $dono] = contaComEquipe();
    $projeto = projetoNa($empresa, $dono, 'Loja');
    ['api_key' => $antiga] = chaveNa($empresa, $dono, ['name' => 'Checkout', 'scopes' => ['projects:read'], 'project_uuids' => [$projeto->uuid]]);
    entrarNa($dono, $empresa);

    $response = confirmarSensivel("/api-keys/{$antiga->uuid}/rotate/code", 'post', "/api-keys/{$antiga->uuid}/rotate", ['grace_minutes' => 60])->assertOk();

    $nova = comoSistema(fn () => ApiKey::query()->where('rotated_from_id', $antiga->id)->sole());
    $antiga = comoSistema(fn () => $antiga->fresh());

    expect($nova->name)->toBe('Checkout')
        ->and($nova->scopes)->toBe(['projects:read'])
        ->and(comoSistema(fn () => $nova->projects()->pluck('uuid')->all()))->toBe([$projeto->uuid])
        ->and($antiga->status)->toBe(ApiKeyStatus::Active)
        ->and($antiga->grace_ends_at?->isFuture())->toBeTrue()
        ->and($response->json('flash.revealedKey.publicKey'))->toBe($nova->public_key)
        ->and($response->json('url'))->toBe('/api-keys')
        ->and(json_encode($response->json('props')))->not->toContain((string) $response->json('flash.revealedKey.secret'));

    // Chave que não está em uso não rotaciona.
    comoSistema(fn () => $nova->forceFill(['status' => ApiKeyStatus::Revoked])->save());
    $this->post("/api-keys/{$nova->uuid}/rotate/code", ['grace_minutes' => 0, 'stage' => 'check'])->assertSessionHasErrors('rotate');
})->group('accounts');

it('revoga e edita os projetos da chave; projeto de outra conta vira erro, nunca vínculo', function () {
    ['empresa' => $empresa, 'dono' => $dono] = contaComEquipe();
    $outra = User::factory()->create();
    $meu = projetoNa($empresa, $dono, 'Meu');
    $alheio = projetoNa(contaPessoal($outra), $outra, 'Alheio');
    ['api_key' => $chave] = chaveNa($empresa, $dono);
    entrarNa($dono, $empresa);

    $this->put("/api-keys/{$chave->uuid}/projects", ['project_uuids' => [$alheio->uuid]])
        ->assertSessionHasErrors(['project_uuids' => __('api_keys.projects.invalid')]);
    expect(comoSistema(fn () => $chave->fresh()->isRestrictedToProjects()))->toBeFalse();

    $this->put("/api-keys/{$chave->uuid}/projects", ['project_uuids' => [$meu->uuid]])
        ->assertRedirect('/api-keys')->assertSessionHas('status', __('panel.api_keys.projects_saved'));
    expect(comoSistema(fn () => $chave->fresh()->projects()->pluck('uuid')->all()))->toBe([$meu->uuid]);

    $this->delete("/api-keys/{$chave->uuid}")->assertRedirect('/api-keys')->assertSessionHas('status', __('panel.api_keys.revoked'));
    expect(comoSistema(fn () => $chave->fresh()->status))->toBe(ApiKeyStatus::Revoked);
})->group('accounts');

it('chave de outra conta: 404 em todo envio (nem confirma que existe)', function () {
    $dono = User::factory()->withTransactionPassword()->create();
    $outra = User::factory()->create();
    ['api_key' => $alheia] = chaveNa(contaPessoal($outra), $outra);
    entrarNa($dono, contaPessoal($dono));

    $this->delete("/api-keys/{$alheia->uuid}")->assertNotFound();
    $this->put("/api-keys/{$alheia->uuid}/projects", ['project_uuids' => []])->assertNotFound();
    $this->post("/api-keys/{$alheia->uuid}/rotate/code", ['grace_minutes' => 0, 'stage' => 'check'])->assertNotFound();
    $this->post("/api-keys/{$alheia->uuid}/rotate", ['grace_minutes' => 0, 'code' => '123456'])->assertNotFound();
    $this->delete('/api-keys/nao-e-uuid')->assertNotFound();

    expect(comoSistema(fn () => $alheia->fresh()->status))->toBe(ApiKeyStatus::Active);
})->group('accounts');

it('a tela é Inertia com o catálogo de escopos e as opções de transição', function () {
    $dono = User::factory()->create();
    entrarNa($dono, contaPessoal($dono));

    $this->get('/api-keys')->assertInertia(fn (Assert $page) => $page
        ->component('api-keys/index')
        ->where('graceOptions', [0, 60, 1440, 10080])
        ->has('scopesCatalog')
        ->has('keys', 0));
})->group('accounts');
