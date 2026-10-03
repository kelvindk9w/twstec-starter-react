<?php

declare(strict_types=1);

use App\Models\User;
use Twstec\Kit\Accounts\Tenancy\Models\Project;

// =============================================================================
// Idempotency-Key na API v1 do starter (twstec/kit-accounts + o middleware
// `idempotent` do twstec/kit-foundation) — ver docs/api.md, "Idempotência".
//
// Os critérios de aceite da issue #20 pela requisição de verdade. A regra
// inteira é coberta nas suítes dos pacotes; a concorrência real (dois
// processos no PostgreSQL) está em IdempotencyConcurrencyTest.
// =============================================================================

it('dois POST com a mesma chave: um projeto e duas respostas idênticas', function (): void {
    $user = User::factory()->create();
    ['api_key' => $key, 'secret_key' => $secret] = chaveNa(contaPessoal($user), $user);
    $headers = ['X-Api-Key' => $key->public_key, 'Authorization' => 'Bearer '.$secret, 'Idempotency-Key' => 'pedido-2026-0001-aaaaaaaa'];

    $first = $this->postJson('/api/v1/projects', ['name' => 'Pedidos'], $headers);
    $second = $this->postJson('/api/v1/projects', ['name' => 'Pedidos'], $headers);

    $first->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
    $second->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertHeader('X-Original-Correlation-Id', (string) $first->headers->get('X-Correlation-Id'));

    expect($second->getContent())->toBe($first->getContent())
        ->and(comoSistema(fn (): int => Project::query()->count()))->toBe(1);
})->group('accounts');

it('mesma chave com corpo diferente: 422 idempotency_key_reused, sem criar', function (): void {
    $user = User::factory()->create();
    ['api_key' => $key, 'secret_key' => $secret] = chaveNa(contaPessoal($user), $user);
    $headers = ['X-Api-Key' => $key->public_key, 'Authorization' => 'Bearer '.$secret, 'Idempotency-Key' => 'pedido-2026-0002-bbbbbbbb'];

    $this->postJson('/api/v1/projects', ['name' => 'Pedidos'], $headers)->assertCreated();

    $this->postJson('/api/v1/projects', ['name' => 'Faturas'], $headers)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'idempotency_key_reused');

    expect(comoSistema(fn (): int => Project::query()->count()))->toBe(1);
})->group('accounts');

it('a mesma chave em duas contas: duas escritas independentes', function (): void {
    $ana = User::factory()->create();
    $bia = User::factory()->create();
    ['api_key' => $keyA, 'secret_key' => $secretA] = chaveNa(contaPessoal($ana), $ana);
    ['api_key' => $keyB, 'secret_key' => $secretB] = chaveNa(contaPessoal($bia), $bia);

    $a = $this->postJson('/api/v1/projects', ['name' => 'Pedidos'], ['X-Api-Key' => $keyA->public_key, 'Authorization' => 'Bearer '.$secretA, 'Idempotency-Key' => 'pedido-2026-0003-cccccccc']);
    $b = $this->postJson('/api/v1/projects', ['name' => 'Pedidos'], ['X-Api-Key' => $keyB->public_key, 'Authorization' => 'Bearer '.$secretB, 'Idempotency-Key' => 'pedido-2026-0003-cccccccc']);

    $a->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
    $b->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

    expect(comoSistema(fn (): int => Project::query()->count()))->toBe(2);
})->group('accounts');

it('chave vencida executa de novo', function (): void {
    $this->freezeSecond();
    $inicio = now()->toImmutable();

    $user = User::factory()->create();
    ['api_key' => $key, 'secret_key' => $secret] = chaveNa(contaPessoal($user), $user);
    $headers = ['X-Api-Key' => $key->public_key, 'Authorization' => 'Bearer '.$secret, 'Idempotency-Key' => 'pedido-2026-0004-dddddddd'];

    $this->postJson('/api/v1/projects', ['name' => 'Pedidos'], $headers)->assertCreated();

    $this->travelTo($inicio->addHours((int) config('idempotency.ttl_hours'))->subSecond());
    $this->postJson('/api/v1/projects', ['name' => 'Pedidos'], $headers)->assertHeader('Idempotent-Replayed', 'true');

    $this->travelTo($inicio->addHours((int) config('idempotency.ttl_hours')));
    $this->postJson('/api/v1/projects', ['name' => 'Pedidos'], $headers)->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

    expect(comoSistema(fn (): int => Project::query()->count()))->toBe(2);
})->group('accounts');
