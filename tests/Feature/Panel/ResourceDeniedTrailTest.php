<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Twstec\Kit\Accounts\ApiKeys\Enums\ApiKeyStatus;
use Twstec\Kit\Accounts\ApiKeys\Models\ApiKey;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;

// =============================================================================
// Telas de CHAVES DE API e de PROJETOS (React): toda recusa fica na trilha.
//
// Requisito do dono: tudo que é tentado no painel fica no banco, inclusive as
// recusas. Cada envio recusa como antes — o mesmo 403 de papel, o mesmo 404
// de chave/projeto fora da conta atual, o mesmo erro de validação do projeto
// de fora da conta no vínculo — e grava `denied` com a ação tentada, quem, a
// conta e o alvo. Quem pode passa sem linha nenhuma.
// =============================================================================

beforeEach(function () {
    Mail::fake();
    config()->set('auth.verification.resend_cooldown_seconds', 0);
    config()->set('security.rate_limit.sensitive', 1000);
});

/**
 * @return array<string, mixed>
 */
function chaveDoFormulario(array $extra = []): array
{
    return ['name' => 'Integração', 'all_scopes' => true, 'scopes' => [], 'project_uuids' => [], 'expires_at' => null, ...$extra];
}

/**
 * @return list<array{0: string, 1: string, 2: string, 3: ?string, 4: string}>
 */
function trilhaDasRecusas(): array
{
    return AuditEvent::query()->orderBy('id')->get()
        ->map(fn (AuditEvent $e): array => [$e->action, $e->outcome->value, $e->context->value, $e->subject_uuid, (string) $e->reason])
        ->all();
}

it('member: cada envio recusado pelo papel dá o mesmo 403 e grava denied', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'membro' => $membro] = contaComEquipe();
    ['api_key' => $chave] = chaveNa($empresa, $dono);
    $projeto = projetoNa($empresa, $dono, 'Da equipe');
    $k = (string) $chave->uuid;
    $p = (string) $projeto->uuid;
    entrarNa($membro, $empresa);

    $this->post('/api-keys/code', chaveDoFormulario(['stage' => 'check']))->assertForbidden();
    $this->post('/api-keys', chaveDoFormulario(['code' => '123456']))->assertForbidden();
    $this->post("/api-keys/{$k}/rotate/code", ['grace_minutes' => 0, 'stage' => 'check'])->assertForbidden();
    $this->post("/api-keys/{$k}/rotate", ['grace_minutes' => 0, 'code' => '123456'])->assertForbidden();
    $this->delete("/api-keys/{$k}")->assertForbidden();
    $this->put("/api-keys/{$k}/projects", ['project_uuids' => []])->assertForbidden();
    $this->delete("/projects/{$p}")->assertForbidden();

    $negado = __('accounts.authorization.denied');

    expect(trilhaDasRecusas())->toBe([
        ['api_key.created', 'denied', 'panel', null, $negado],
        ['api_key.created', 'denied', 'panel', null, $negado],
        ['api_key.rotated', 'denied', 'panel', $k, $negado],
        ['api_key.rotated', 'denied', 'panel', $k, $negado],
        ['api_key.revoked', 'denied', 'panel', $k, $negado],
        ['api_key.projects_synced', 'denied', 'panel', $k, $negado],
        ['project.deleted', 'denied', 'panel', $p, $negado],
    ])
        ->and(AuditEvent::query()->distinct()->pluck('actor_uuid')->all())->toBe([(string) $membro->uuid])
        ->and(AuditEvent::query()->distinct()->pluck('tenant_uuid')->all())->toBe([(string) $empresa->uuid])
        ->and(comoSistema(fn () => $chave->fresh()->status))->toBe(ApiKeyStatus::Active)
        ->and(comoSistema(fn () => Project::query()->whereKey($projeto->id)->exists()))->toBeTrue();
    Mail::assertNothingQueued();
})->group('accounts');

it('chave e projeto fora da conta atual: o mesmo 404 (de outra conta ou inexistente) e cada envio grava denied', function () {
    $dono = User::factory()->withTransactionPassword()->create();
    $outra = User::factory()->create();
    ['api_key' => $alheia] = chaveNa(contaPessoal($outra), $outra);
    $alheio = projetoNa(contaPessoal($outra), $outra, 'Alheio');
    $k = (string) $alheia->uuid;
    $p = (string) $alheio->uuid;
    $nada = (string) Str::uuid();
    entrarNa($dono, contaPessoal($dono));

    $this->post("/api-keys/{$k}/rotate/code", ['grace_minutes' => 0, 'stage' => 'check'])->assertNotFound();
    $this->post("/api-keys/{$k}/rotate", ['grace_minutes' => 0, 'code' => '123456'])->assertNotFound();
    $this->delete("/api-keys/{$k}")->assertNotFound();
    $this->put("/api-keys/{$k}/projects", ['project_uuids' => []])->assertNotFound();
    $this->delete("/api-keys/{$nada}")->assertNotFound();
    $this->delete('/api-keys/nao-e-uuid')->assertNotFound();
    $this->patch("/projects/{$p}", ['name' => 'Tomado'])->assertNotFound();
    $this->delete("/projects/{$p}")->assertNotFound();
    $this->delete("/projects/{$nada}")->assertNotFound();

    $fora = __('accounts.authorization.not_found');

    expect(trilhaDasRecusas())->toBe([
        ['api_key.rotated', 'denied', 'panel', $k, $fora],
        ['api_key.rotated', 'denied', 'panel', $k, $fora],
        ['api_key.revoked', 'denied', 'panel', $k, $fora],
        ['api_key.projects_synced', 'denied', 'panel', $k, $fora],
        ['api_key.revoked', 'denied', 'panel', $nada, $fora],
        ['api_key.revoked', 'denied', 'panel', null, $fora],
        ['project.updated', 'denied', 'panel', $p, $fora],
        ['project.deleted', 'denied', 'panel', $p, $fora],
        ['project.deleted', 'denied', 'panel', $nada, $fora],
    ])
        ->and(AuditEvent::query()->distinct()->pluck('actor_uuid')->all())->toBe([(string) $dono->uuid])
        ->and(AuditEvent::query()->distinct()->pluck('tenant_uuid')->all())->toBe([(string) contaPessoal($dono)->uuid])
        ->and(comoSistema(fn () => $alheia->fresh()->status))->toBe(ApiKeyStatus::Active)
        ->and(comoSistema(fn () => $alheio->fresh()->name))->toBe('Alheio');
})->group('accounts');

it('projeto de outra conta no vínculo e na criação da chave: o mesmo erro de validação e a tentativa grava denied', function () {
    ['empresa' => $empresa, 'admin' => $admin] = contaComEquipe();
    ['api_key' => $chave] = chaveNa($empresa, $admin);
    $outra = User::factory()->create();
    $alheio = projetoNa(contaPessoal($outra), $outra, 'Alheio');
    entrarNa($admin, $empresa);

    $this->put("/api-keys/{$chave->uuid}/projects", ['project_uuids' => [$alheio->uuid]])
        ->assertSessionHasErrors(['project_uuids' => __('api_keys.projects.invalid')]);
    $this->post('/api-keys/code', chaveDoFormulario(['stage' => 'check', 'project_uuids' => [$alheio->uuid]]))
        ->assertSessionHasErrors(['project_uuids' => __('api_keys.projects.invalid')]);

    $invalido = __('api_keys.projects.invalid');

    expect(trilhaDasRecusas())->toBe([
        ['api_key.projects_synced', 'denied', 'panel', (string) $chave->uuid, $invalido],
        ['api_key.created', 'denied', 'panel', null, $invalido],
    ])
        ->and(comoSistema(fn () => $chave->fresh()->projects()->count()))->toBe(0)
        ->and(comoSistema(fn () => ApiKey::query()->count()))->toBe(1);
    Mail::assertNothingQueued();
})->group('accounts');

it('quem pode passa sem linha nenhuma na trilha', function () {
    ['empresa' => $empresa, 'dono' => $dono, 'membro' => $membro] = contaComEquipe();
    ['api_key' => $chave] = chaveNa($empresa, $dono);
    $projeto = projetoNa($empresa, $dono, 'Da equipe');

    entrarNa($dono, $empresa);
    $this->post('/api-keys/code', chaveDoFormulario(['stage' => 'check']))->assertSessionHasNoErrors();
    $this->post("/api-keys/{$chave->uuid}/rotate/code", ['grace_minutes' => 0, 'stage' => 'check'])->assertSessionHasNoErrors();
    $this->put("/api-keys/{$chave->uuid}/projects", ['project_uuids' => [$projeto->uuid]])->assertSessionHasNoErrors();
    $this->patch("/projects/{$projeto->uuid}", ['name' => 'Renomeado'])->assertSessionHasNoErrors();

    entrarNa($membro, $empresa);
    $this->post('/projects', ['name' => 'Do membro'])->assertSessionHasNoErrors();
    $this->patch("/projects/{$projeto->uuid}", ['name' => 'De novo'])->assertSessionHasNoErrors();

    expect(AuditEvent::query()->count())->toBe(0);
})->group('accounts');
