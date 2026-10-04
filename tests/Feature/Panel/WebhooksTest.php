<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;
use Twstec\Kit\Webhooks\Models\WebhookDeliveryAttempt;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Security\HostResolver;
use Twstec\Kit\Webhooks\Webhooks;

// =============================================================================
// Webhooks pelo painel React: o caminho HTTP/Inertia do starter sobre as
// Actions do twstec/kit-webhooks (a regra — SSRF, assinatura, fila, novas
// tentativas — é coberta pelo pacote). Aqui: papel conferido em CADA envio,
// conta alheia = 404, a confirmação sensível de verdade (senha → código →
// token consumido no servidor), o SEGREDO exibido uma vez (só na resposta
// imediata, fora das props e da sessão), o log de entregas e o reenvio
// auditado. Nada sai da máquina: o DNS é de mentira e o envio, Http::fake.
// Nada declarado no topo do arquivo (o módulo é opcional).
// =============================================================================

beforeEach(function () {
    Mail::fake();
    config()->set('auth.verification.resend_cooldown_seconds', 0);
    config()->set('security.rate_limit.sensitive', 1000);
    config()->set('webhooks.events', ['order.created', 'order.shipped']);

    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return match ($host) {
                'hooks.example.com' => ['93.184.215.14'],
                'interno.example.com' => ['10.0.0.5'],
                default => [],
            };
        }
    });
});

/**
 * @return array<string, mixed>
 */
function dadosDoWebhook(array $extra = []): array
{
    return ['name' => 'Receptor de pedidos', 'url' => 'https://hooks.example.com/webhooks', 'events' => ['order.created'], 'project' => '', ...$extra];
}

/**
 * @return array{endpoint: WebhookEndpoint, secret: string}
 */
function criarWebhookPelaTela(mixed $test): array
{
    $response = confirmarSensivel('/webhooks/code', 'post', '/webhooks', dadosDoWebhook())->assertOk();

    return ['endpoint' => comoSistema(fn () => WebhookEndpoint::query()->latest('id')->firstOrFail()), 'secret' => (string) $response->json('flash.revealedWebhookSecret.secret')];
}

it('exige sessão', function () {
    $this->get('/webhooks')->assertRedirect(route('login'));
})->group('accounts', 'webhooks');

it('cria pela tela com a confirmação sensível e mostra o segredo UMA vez — fora das props e da sessão, cifrado no banco', function () {
    ['empresa' => $empresa, 'admin' => $admin] = contaComEquipe();
    entrarNa($admin, $empresa);

    // Passo 1: a conferência (formulário + destino) não manda código.
    $this->withHeaders(inertiaHeaders())->post('/webhooks/code', dadosDoWebhook(['stage' => 'check']))
        ->assertRedirect('/webhooks')->assertSessionHasNoErrors();
    Mail::assertNothingQueued();

    $response = confirmarSensivel('/webhooks/code', 'post', '/webhooks', dadosDoWebhook())->assertOk();

    $endpoint = comoSistema(fn () => WebhookEndpoint::query()->sole());
    $secret = $response->json('flash.revealedWebhookSecret.secret');

    expect($endpoint->account_id)->toBe($empresa->id)
        ->and($endpoint->created_by)->toBe($admin->id)
        ->and($secret)->toBeString()->toStartWith('whsk_')
        ->and($endpoint->secret)->toBe($secret)
        ->and(DB::table('webhook_endpoints')->value('secret'))->not->toContain($secret)
        ->and(Crypt::decryptString((string) DB::table('webhook_endpoints')->value('secret')))->toBe($secret)
        ->and($response->json('url'))->toBe('/webhooks')
        ->and(json_encode($response->json('props')))->not->toContain($secret)
        ->and(json_encode(session()->all()))->not->toContain($secret);

    $depois = $this->withHeaders(inertiaHeaders())->get('/webhooks')->assertOk();

    expect((string) $depois->getContent())->not->toContain($secret)
        ->and($depois->json('component'))->toBe('webhooks/index')
        ->and($depois->json('props.endpoints.0.uuid'))->toBe($endpoint->uuid)
        ->and($depois->json('props.canManage'))->toBeTrue()
        ->and($depois->json('flash'))->toBeEmpty();
})->group('accounts', 'webhooks');

it('SSRF: destino na rede interna é recusado na conferência, sem mandar código — e fica na trilha', function () {
    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));

    foreach (['https://127.0.0.1/hook', 'https://169.254.169.254/latest/meta-data', 'https://10.1.2.3/', 'https://[::1]/', 'https://interno.example.com/hook'] as $url) {
        $this->post('/webhooks/code', dadosDoWebhook(['url' => $url, 'stage' => 'check']))->assertSessionHasErrors('url');
    }

    Mail::assertNothingQueued();

    expect(comoSistema(fn () => WebhookEndpoint::query()->count()))->toBe(0)
        ->and(AuditEvent::query()->where('action', 'webhook_endpoint.created')->where('outcome', 'denied')->count())->toBe(5);
})->group('accounts', 'webhooks');

it('member vê, mas não gere: 403 em cada envio', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'membro' => $membro] = contaComEquipe();
    entrarNa($dono, $empresa);
    ['endpoint' => $endpoint] = criarWebhookPelaTela($this);

    test()->flushSession();
    entrarNa($membro, $empresa);

    $this->withHeaders(inertiaHeaders())->get('/webhooks')->assertOk()
        ->assertJsonPath('props.canManage', false)
        ->assertJsonCount(1, 'props.endpoints');

    $this->post('/webhooks/code', dadosDoWebhook(['stage' => 'check']))->assertForbidden();
    $this->post("/webhooks/{$endpoint->uuid}/secret/code", ['action' => 'reveal', 'stage' => 'check'])->assertForbidden();
    $this->post("/webhooks/{$endpoint->uuid}/status", ['active' => false])->assertForbidden();
    $this->post("/webhooks/{$endpoint->uuid}/test")->assertForbidden();
    $this->delete("/webhooks/{$endpoint->uuid}")->assertForbidden();

    expect(comoSistema(fn () => WebhookEndpoint::query()->sole()->isActive()))->toBeTrue();
})->group('accounts', 'webhooks');

it('endpoint de outra conta: 404 (o mesmo do inexistente)', function () {
    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));
    ['endpoint' => $endpoint] = criarWebhookPelaTela($this);

    $intruso = User::factory()->withTransactionPassword()->create();
    test()->flushSession();
    entrarNa($intruso, contaPessoal($intruso));

    $this->post("/webhooks/{$endpoint->uuid}/test")->assertNotFound();
    $this->delete("/webhooks/{$endpoint->uuid}")->assertNotFound();
})->group('accounts', 'webhooks');

it('revelar e rotacionar o segredo pedem a confirmação sensível e ficam na trilha', function () {
    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));
    ['endpoint' => $endpoint, 'secret' => $secret] = criarWebhookPelaTela($this);

    $revelado = confirmarSensivel("/webhooks/{$endpoint->uuid}/secret/code", 'post', "/webhooks/{$endpoint->uuid}/reveal", ['action' => 'reveal'])->assertOk();

    expect($revelado->json('flash.revealedWebhookSecret.secret'))->toBe($secret);

    $rotacionado = confirmarSensivel("/webhooks/{$endpoint->uuid}/secret/code", 'post', "/webhooks/{$endpoint->uuid}/rotate", ['action' => 'rotate', 'overlap_minutes' => 60])->assertOk();
    $novo = (string) $rotacionado->json('flash.revealedWebhookSecret.secret');

    expect($novo)->not->toBe($secret)
        ->and(comoSistema(fn () => $endpoint->fresh()->signingSecrets()))->toBe([$novo, $secret])
        ->and(AuditEvent::query()->where('action', 'webhook_endpoint.secret_revealed')->where('outcome', 'success')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'webhook_endpoint.secret_rotated')->where('outcome', 'success')->exists())->toBeTrue()
        ->and(json_encode(AuditEvent::query()->get()))->not->toContain($secret)->not->toContain($novo);

    // Sem o código, não revela.
    $this->post("/webhooks/{$endpoint->uuid}/reveal", ['action' => 'reveal'])->assertSessionHasErrors('code');
})->group('accounts', 'webhooks');

it('enviar teste, ver o log de entregas e REENVIAR (auditado)', function () {
    Http::fake(['hooks.example.com/*' => Http::sequence()->push('{"ok":false}', 500)->push('{"ok":true}', 200)]);

    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));
    ['endpoint' => $endpoint] = criarWebhookPelaTela($this);

    $this->post("/webhooks/{$endpoint->uuid}/test")->assertRedirect("/webhooks?endpoint={$endpoint->uuid}");

    $pagina = $this->withHeaders(inertiaHeaders())->get("/webhooks?endpoint={$endpoint->uuid}")->assertOk();
    $entrega = $pagina->json('props.deliveries.0');

    expect($entrega['event']['type'])->toBe('webhook.ping')
        ->and($entrega['status'])->toBe('retrying')
        ->and($entrega['attempt_log'][0]['response_status'])->toBe(500);

    $this->post("/webhooks/deliveries/{$entrega['uuid']}/resend")->assertRedirect();

    $tentativas = comoSistema(fn () => WebhookDeliveryAttempt::query()->orderBy('attempt')->get());
    $trilha = AuditEvent::query()->where('action', 'webhook_delivery.resent')->sole();

    expect($tentativas)->toHaveCount(2)
        ->and($tentativas[1]->manual)->toBeTrue()
        ->and($tentativas[1]->created_by)->toBe($dono->id)
        ->and($tentativas[1]->response_status)->toBe(200)
        ->and($trilha->actor_uuid)->toBe($dono->uuid)
        ->and($trilha->subject_uuid)->toBe($entrega['uuid']);
})->group('accounts', 'webhooks');

it('o evento do aplicativo aparece no log da conta (e só nele)', function () {
    Http::fake(['*' => Http::response('', 204)]);

    $dono = User::factory()->withTransactionPassword()->create();
    entrarNa($dono, contaPessoal($dono));
    criarWebhookPelaTela($this);

    Webhooks::dispatch(contaPessoal($dono), 'order.created', ['order' => ['id' => 'ped-1']]);

    $this->withHeaders(inertiaHeaders())->get('/webhooks')->assertOk()
        ->assertJsonPath('props.deliveries.0.event.type', 'order.created')
        ->assertJsonPath('props.deliveries.0.status', 'succeeded');

    // O corpo do evento nunca vai para a tela.
    expect((string) $this->withHeaders(inertiaHeaders())->get('/webhooks')->getContent())->not->toContain('ped-1');
})->group('accounts', 'webhooks');
