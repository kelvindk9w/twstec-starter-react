<?php

declare(strict_types=1);

use App\Models\User;

// =============================================================================
// Acesso ao dashboard do Horizon (/horizon): SÓ admin com a permissão
// `horizon.access` no papel (gate viewHorizon → HorizonAccess do
// twstec/kit-admin; de fábrica, owner e operations). O Horizon mostra o
// payload dos jobs e refaz/apaga job: suporte e auditoria ficam de fora. O
// ambiente de teste é `testing`, então o gate vale de verdade (em `local` o
// pacote libera geral).
// =============================================================================

it('nega com 403 visitante e usuário sem a flag admin', function () {
    $this->get('/horizon')->assertForbidden();

    $this->actingAs(User::factory()->create())->get('/horizon')->assertForbidden();
});

it('papel sem `horizon.access` não abre o /horizon: suporte e auditoria recebem 403; operação e dono abrem', function (string $role, int $status) {
    $admin = User::factory()->admin($role)->create();

    $this->actingAs($admin)->get('/horizon')->assertStatus($status);

    // A API de onde o dashboard lê os payloads segue o mesmo gate.
    if ($status === 403) {
        $this->actingAs($admin)->getJson('/horizon/api/jobs/failed')->assertForbidden();
    }
})->with([
    'suporte' => ['support', 403],
    'auditoria' => ['auditor', 403],
    'operação' => ['operations', 200],
    'dono' => ['owner', 200],
])->group('admin');
