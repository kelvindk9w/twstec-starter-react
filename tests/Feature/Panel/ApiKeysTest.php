<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\ApiKeys\Confirmation\ApiKeyCreationConfirmation;
use Twstec\Kit\Accounts\ApiKeys\Enums\ApiKeyStatus;
use Twstec\Kit\Accounts\ApiKeys\Models\ApiKey;
use Twstec\Kit\Accounts\ApiKeys\Services\ApiKeyService;
use Twstec\Kit\Accounts\ApiKeys\Support\ApiKeyHasher;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Models\SensitiveActionToken;

// =============================================================================
// Chaves de API pelo painel React: o caminho HTTP/Inertia do starter sobre o
// ApiKeyService e o SensitiveActionService (a regra, coberta pelo pacote e
// pelo Livewire). Aqui: papel conferido em CADA envio, conta alheia = 404, a
// confirmação sensível de verdade (senha → código do e-mail → token consumido
// no servidor) e a SECRETA exibida UMA vez — só na resposta imediata, fora das
// props, nunca na listagem, nunca na sessão.
//
// A CRIAÇÃO segue a confirmação do ambiente das chaves
// (`api_keys.creation_confirmation`): em `live`, senha de transação + código;
// em `test` (o do .env.example), só o código. Cada teste diz o ambiente.
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
    return ['name' => 'Integração do checkout', 'all_scopes' => true, 'scopes' => [], 'project_uuids' => [], 'expiry_unit' => 'none', ...$extra];
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

it('cria pela tela com a confirmação sensível (live) e mostra a secreta UMA vez — só na resposta imediata', function () {
    config()->set('api_keys.environment', 'live');
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
    config()->set('api_keys.environment', 'live');
    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));

    $this->post('/api-keys/code', dadosDaChave(['all_scopes' => false, 'stage' => 'check']))
        ->assertSessionHasErrors('scopes');

    confirmarSensivel('/api-keys/code', 'post', '/api-keys', dadosDaChave(['all_scopes' => false, 'scopes' => ['projects:read']]))->assertOk();

    expect(comoSistema(fn () => ApiKey::query()->sole()->scopes))->toBe(['projects:read']);
})->group('accounts');

it('"todas as permissões" é escolha explícita: pedido sem o campo é recusado — a chave nunca nasce com *:* por omissão', function () {
    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));

    $semEscolha = dadosDaChave();
    unset($semEscolha['all_scopes']);

    $this->post('/api-keys/code', [...$semEscolha, 'stage' => 'check'])->assertSessionHasErrors('all_scopes');
    $this->post('/api-keys', [...$semEscolha, 'code' => '123456'])->assertSessionHasErrors('all_scopes');

    expect(comoSistema(fn () => ApiKey::query()->count()))->toBe(0);
    Mail::assertNothingQueued();
})->group('accounts');

it('live: sem senha de transação definida, a tela avisa e nada é enviado', function () {
    config()->set('api_keys.environment', 'live');
    $dono = User::factory()->create();
    entrarNa($dono, contaPessoal($dono));

    $this->post('/api-keys/code', dadosDaChave(['stage' => 'check']))
        ->assertSessionHasErrors(['name' => __('panel.api_keys.sensitive_requires_password')]);

    Mail::assertNothingQueued();
})->group('accounts');

it('live: senha de transação errada (ou nenhuma) e código errado: nenhuma chave', function () {
    config()->set('api_keys.environment', 'live');
    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));

    $this->post('/api-keys/code', dadosDaChave(['stage' => 'send', 'transaction_password' => 'errada-123']))
        ->assertSessionHasErrors('transaction_password');
    // Live nunca afrouxa: sem a senha, nenhum código sai.
    $this->post('/api-keys/code', dadosDaChave(['stage' => 'send']))
        ->assertSessionHasErrors('transaction_password');
    Mail::assertNothingQueued();

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

// =============================================================================
// CONFIRMAÇÃO DA CRIAÇÃO POR AMBIENTE e VALIDADE ESCOLHIDA NA CRIAÇÃO
// =============================================================================

it('a tela recebe a política da criação e a escolha da validade com os tetos', function (string $ambiente, bool $pedeSenha) {
    config()->set('api_keys.environment', $ambiente);
    config()->set('api_keys.expiry.max_hours', 48);
    config()->set('api_keys.expiry.max_days', 90);
    $dono = User::factory()->create();
    entrarNa($dono, contaPessoal($dono));

    $this->get('/api-keys')->assertInertia(fn (Assert $page) => $page
        ->component('api-keys/index')
        ->where('creationRequiresTransactionPassword', $pedeSenha)
        ->where('expiryOptions', ['units' => ['none', 'hours', 'days'], 'maxHours' => 48, 'maxDays' => 90]));
})->with([
    'live' => ['live', true],
    'test' => ['test', false],
])->group('accounts');

it('test: cria só com o código por e-mail — sem senha de transação, sem token de ação sensível', function () {
    config()->set('api_keys.environment', 'test');
    $dono = User::factory()->create(); // sem senha de transação: a criação não pede
    entrarNa($dono, contaPessoal($dono));

    $this->post('/api-keys/code', dadosDaChave(['stage' => 'check']))->assertSessionHasNoErrors();
    Mail::assertNothingQueued();

    $this->post('/api-keys/code', dadosDaChave(['stage' => 'send']))
        ->assertRedirect('/api-keys')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('auth.verification_code.sent'));

    /** @var VerificationCodeMail $mail */
    $mail = Mail::queued(VerificationCodeMail::class)->last();
    expect($mail->purpose)->toBe(VerificationPurpose::CodeConfirmation);

    $response = $this->withHeaders(inertiaHeaders())->post('/api-keys', dadosDaChave(['code' => ultimoCodigo()]))->assertOk();

    $chave = comoSistema(fn () => ApiKey::query()->sole());

    expect($response->json('flash.revealedKey.publicKey'))->toBe($chave->public_key)
        ->and($chave->expires_at)->toBeNull()
        ->and(SensitiveActionToken::query()->count())->toBe(0);

    // O código é de uso único.
    $this->post('/api-keys', dadosDaChave(['code' => ultimoCodigo()]))->assertSessionHasErrors('code');
    expect(comoSistema(fn () => ApiKey::query()->count()))->toBe(1);
})->group('accounts');

it('test: código errado não cria; a rotação continua pedindo a senha de transação', function () {
    config()->set('api_keys.environment', 'test');
    $dono = User::factory()->withTransactionPassword()->create();
    ['api_key' => $chave] = chaveNa(contaPessoal($dono), $dono);
    entrarNa($dono, contaPessoal($dono));

    $this->post('/api-keys/code', dadosDaChave(['stage' => 'send']))->assertSessionHasNoErrors();
    $errado = ultimoCodigo() === '000000' ? '111111' : '000000';
    $this->post('/api-keys', dadosDaChave(['code' => $errado]))->assertSessionHasErrors('code');

    $this->post("/api-keys/{$chave->uuid}/rotate/code", ['grace_minutes' => 0, 'stage' => 'send'])
        ->assertSessionHasErrors('transaction_password');

    Mail::assertQueuedCount(1);
    expect(comoSistema(fn () => ApiKey::query()->count()))->toBe(1);
})->group('accounts');

it('a validade escolhida na criação: sem validade, N horas ou N dias', function (array $validade, ?string $esperado) {
    config()->set('api_keys.environment', 'test');
    $this->travelTo(CarbonImmutable::parse('2026-11-14 10:00:00', 'UTC'));
    $dono = User::factory()->create();
    entrarNa($dono, contaPessoal($dono));

    $dados = dadosDaChave($validade);

    $this->post('/api-keys/code', [...$dados, 'stage' => 'send'])->assertSessionHasNoErrors();
    $this->withHeaders(inertiaHeaders())->post('/api-keys', [...$dados, 'code' => ultimoCodigo()])->assertOk();

    $chave = comoSistema(fn () => ApiKey::query()->sole());

    expect($chave->expires_at?->utc()->toIso8601String())->toBe($esperado);
})->with([
    'sem validade' => [['expiry_unit' => 'none'], null],
    '6 horas' => [['expiry_unit' => 'hours', 'expiry_amount' => 6], '2026-11-14T16:00:00+00:00'],
    '30 dias' => [['expiry_unit' => 'days', 'expiry_amount' => '30'], '2026-12-14T10:00:00+00:00'],
])->group('accounts');

it('validade fora do teto, sem quantidade, desconhecida ou a data antiga: recusada antes de gastar o código', function (array $validade, string $campo) {
    config()->set('api_keys.environment', 'test');
    config()->set('api_keys.expiry.max_hours', 48);
    config()->set('api_keys.expiry.max_days', 90);
    $dono = User::factory()->create();
    entrarNa($dono, contaPessoal($dono));

    $semValidade = dadosDaChave();
    unset($semValidade['expiry_unit']);

    $this->post('/api-keys/code', [...$semValidade, ...$validade, 'stage' => 'send'])->assertSessionHasErrors($campo);
    Mail::assertNothingQueued();

    // Pedido válido manda o código; a criação com a validade errada é recusada
    // e o código continua valendo para a certa.
    $this->post('/api-keys/code', dadosDaChave(['stage' => 'send']))->assertSessionHasNoErrors();
    $codigo = ultimoCodigo();
    $this->post('/api-keys', [...$semValidade, ...$validade, 'code' => $codigo])->assertSessionHasErrors($campo);
    expect(comoSistema(fn () => ApiKey::query()->count()))->toBe(0);

    $this->withHeaders(inertiaHeaders())->post('/api-keys', dadosDaChave(['code' => $codigo]))->assertOk();
    expect(comoSistema(fn () => ApiKey::query()->count()))->toBe(1);
})->with([
    '49 horas' => [['expiry_unit' => 'hours', 'expiry_amount' => 49], 'expiry_amount'],
    '91 dias' => [['expiry_unit' => 'days', 'expiry_amount' => 91], 'expiry_amount'],
    'horas sem quantidade' => [['expiry_unit' => 'hours'], 'expiry_amount'],
    'unidade desconhecida' => [['expiry_unit' => 'weeks', 'expiry_amount' => 1], 'expiry_unit'],
    'só a data antiga' => [['expires_at' => '2030-01-01 00:00:00'], 'expiry_unit'],
])->group('accounts');

it('a chave de SESSÃO não aparece na lista do painel', function () {
    config()->set('api_keys.environment', 'test');
    $dono = User::factory()->create();
    $conta = contaPessoal($dono);
    chaveNa($conta, $dono, ['name' => 'Chave comum']);

    // A chave de sessão nasce pela API do pacote (como a "Experimente" de
    // uma documentação), na sessão de verdade da pessoa.
    $session = app('session.store');
    $session->start();
    Auth::guard('web')->login($dono);

    $confirmation = app(ApiKeyCreationConfirmation::class);
    $confirmation->sendCode($dono);
    $prova = $confirmation->confirm($dono, ultimoCodigo());

    ['api_key' => $daSessao] = Accounts::actingAs($conta, fn (): array => app(ApiKeyService::class)
        ->createForSession($dono, $session, ['name' => 'Chave da sessão'], $prova), $dono);

    entrarNa($dono, $conta);

    $response = $this->withHeaders(inertiaHeaders())->get('/api-keys')->assertOk()
        ->assertJsonCount(1, 'props.keys')
        ->assertJsonPath('props.keys.0.name', 'Chave comum');

    expect((string) $response->getContent())->not->toContain($daSessao->public_key);
})->group('accounts');
