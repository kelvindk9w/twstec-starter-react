<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Panel\Concerns\ConfirmsSensitiveAction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Twstec\Kit\Accounts\Account\Enums\AccountAbility;
use Twstec\Kit\Accounts\Account\Support\AccountResourceGuard;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\ApiKeys\Enums\ApiKeyAttempt;
use Twstec\Kit\Accounts\ApiKeys\Enums\ApiKeyStatus;
use Twstec\Kit\Accounts\ApiKeys\Http\Requests\StoreApiKeyRequest;
use Twstec\Kit\Accounts\ApiKeys\Models\ApiKey;
use Twstec\Kit\Accounts\ApiKeys\Services\ApiKeyService;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Auth\Services\SensitiveActionService;

/**
 * Chaves de API — a mesma tela do starter Livewire (App\Livewire\ApiKeys\Index)
 * em envios HTTP: listar, criar (escopos + vínculo com projetos), a secreta
 * exibida UMA vez, rotacionar (com período de transição), revogar e editar
 * os projetos da chave.
 *
 * As chaves são da CONTA ATUAL (o escopo das contas filtra toda consulta:
 * uuid de outra conta = 404). Gerir exige owner ou admin
 * (AccountAbility::ManageApiKeys) — conferido em CADA envio, não só no botão.
 * Criar e rotacionar são AÇÕES SENSÍVEIS (senha de transação → código → token
 * consumido aqui, como o middleware `sensitive.token` faria na API).
 *
 * Toda recusa fica na trilha (AccountResourceGuard), com a ação tentada: o
 * papel que não permite (403), a chave que não está na conta atual (o mesmo
 * 404 de sempre) e o projeto de fora da conta no vínculo (o mesmo erro de
 * validação).
 *
 * A SECRETA: só existe na resposta IMEDIATA da criação/rotação, como dado de
 * uma resposta só do Inertia (`flash` da página, que o Inertia não guarda no
 * histórico do navegador) — nunca numa prop, nunca na sessão depois da
 * resposta, nunca na listagem. No banco só fica o hash.
 */
final class ApiKeysController implements HasMiddleware
{
    use ConfirmsSensitiveAction;

    /** Opções de transição da rotação (minutos) — as mesmas do Livewire. */
    public const GRACE_OPTIONS = [0, 60, 1440, 10080];

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive', only: ['code', 'store', 'rotateCode', 'rotate'])];
    }

    public function index(): Response
    {
        return $this->page();
    }

    /**
     * Criação, passos 1 e 2: confere o formulário (e o papel, e a senha de
     * transação definida) e, com `stage=send`, manda o código.
     *
     * @throws ValidationException
     */
    public function code(Request $request): RedirectResponse
    {
        $this->authorizeManage(ApiKeyAttempt::Created);
        $this->validateKeyForm($request);
        $this->resolveProjects($request, ApiKeyAttempt::Created);
        $this->requireTransactionPassword($request, 'name', __('panel.api_keys.sensitive_requires_password'));

        return $this->afterStage($request);
    }

    /**
     * Criação, passo 3: código → token → a chave. A resposta é a própria tela,
     * com a secreta UMA vez.
     *
     * @throws ValidationException
     */
    public function store(Request $request, ApiKeyService $apiKeys): Response
    {
        $this->authorizeManage(ApiKeyAttempt::Created);
        $validated = $this->validateKeyForm($request);
        $this->resolveProjects($request, ApiKeyAttempt::Created);

        $this->consume($request);

        $result = $apiKeys->create($this->user($request), [
            'name' => $validated['name'],
            'scopes' => $request->boolean('all_scopes', true) ? null : array_values($validated['scopes'] ?? []),
            'expires_at' => ($validated['expires_at'] ?? null) !== null
                ? Carbon::parse($validated['expires_at'])->toDateTimeString()
                : null,
            'project_uuids' => ($validated['project_uuids'] ?? []) !== [] ? array_values($validated['project_uuids']) : null,
        ]);

        return $this->page($result);
    }

    /**
     * Rotação, passos 1 e 2 (a chave tem de estar em uso).
     *
     * @throws ValidationException
     */
    public function rotateCode(Request $request, string $key): RedirectResponse
    {
        $this->authorizeManage(ApiKeyAttempt::Rotated, $key);
        $this->rotatableKey($key);
        $this->validateGrace($request);

        return $this->afterStage($request);
    }

    /**
     * Rotação, passo 3: a nova chave herda nome, escopos e projetos; a antiga
     * morre na hora ou depois do período escolhido.
     *
     * @throws ValidationException
     */
    public function rotate(Request $request, string $key, ApiKeyService $apiKeys): Response
    {
        $this->authorizeManage(ApiKeyAttempt::Rotated, $key);
        $current = $this->rotatableKey($key);
        $grace = $this->validateGrace($request);

        $this->consume($request);

        return $this->page($apiKeys->rotate($current, $grace));
    }

    /**
     * Revogar (irreversível; a confirmação é da tela).
     */
    public function revoke(string $key, ApiKeyService $apiKeys): RedirectResponse
    {
        $this->authorizeManage(ApiKeyAttempt::Revoked, $key);

        $apiKeys->revoke($this->findKey($key, ApiKeyAttempt::Revoked));

        return to_route('panel.api-keys')->with('status', __('panel.api_keys.revoked'));
    }

    /**
     * Vínculo chave ↔ projetos (lista vazia = conta toda). Projeto de outra
     * conta vira erro de validação — nunca vínculo.
     *
     * @throws ValidationException
     */
    public function projects(Request $request, string $key, ApiKeyService $apiKeys): RedirectResponse
    {
        $this->authorizeManage(ApiKeyAttempt::ProjectsSynced, $key);
        $apiKey = $this->findKey($key, ApiKeyAttempt::ProjectsSynced);

        $request->validate([
            'project_uuids' => ['array'],
            'project_uuids.*' => ['uuid'],
        ]);

        $apiKeys->syncProjects($apiKey, $this->resolveProjects($request, ApiKeyAttempt::ProjectsSynced, (string) $apiKey->uuid));

        return to_route('panel.api-keys')->with('status', __('panel.api_keys.projects_saved'));
    }

    // =========================================================================
    // Internos
    // =========================================================================

    /**
     * A tela. `$revealed` é o resultado da criação/rotação: a secreta vai só
     * nesta resposta, fora das props (dado de uma resposta só do Inertia).
     *
     * @param  array{api_key: ApiKey, secret_key: string}|null  $revealed
     */
    private function page(?array $revealed = null): Response
    {
        $timezone = platform()->displayTimezone;

        $keys = ApiKey::query()
            ->with('projects:projects.id,projects.uuid,projects.name')
            ->latest()
            ->get()
            ->map(static fn (ApiKey $key): array => [
                'uuid' => (string) $key->uuid,
                'name' => (string) $key->name,
                'publicKey' => (string) $key->public_key,
                'status' => $key->status->value,
                'statusLabel' => $key->grace_ends_at !== null && $key->grace_ends_at->isFuture()
                    ? __('panel.api_keys.status_grace')
                    : __('panel.api_keys.status_'.$key->status->value),
                'usable' => $key->isUsable(),
                'restricted' => $key->isRestrictedToProjects(),
                'projects' => $key->projects->map(static fn (Project $p): array => ['uuid' => (string) $p->uuid, 'name' => (string) $p->name])->values()->all(),
                'lastUsedAt' => $key->last_used_at?->setTimezone($timezone)->format('d/m/Y H:i'),
                'expiresAt' => $key->expires_at?->setTimezone($timezone)->format('d/m/Y H:i'),
            ])
            ->values()
            ->all();

        if ($revealed !== null) {
            // A resposta de um POST vira a tela de chaves: o endereço é o da
            // lista (recarregar a página não repete a rotação).
            Inertia::resolveUrlUsing(static fn (): string => route('panel.api-keys', absolute: false));
        }

        $response = Inertia::render('api-keys/index', [
            'keys' => $keys,
            'projects' => Project::query()->orderBy('name')->get()
                ->map(static fn (Project $p): array => ['uuid' => (string) $p->uuid, 'name' => (string) $p->name])
                ->values()->all(),
            'scopesCatalog' => (array) config('api_keys.scopes_catalog', []),
            'graceOptions' => self::GRACE_OPTIONS,
            'canManageKeys' => Accounts::can(AccountAbility::ManageApiKeys),
        ]);

        if ($revealed !== null) {
            // O endereço fica gravado nesta resposta; as próximas voltam ao padrão.
            Inertia::resolveUrlUsing(null);

            $response->flash('revealedKey', [
                'publicKey' => (string) $revealed['api_key']->public_key,
                'secret' => $revealed['secret_key'],
            ]);
        }

        return $response;
    }

    /**
     * Código → token → consumido aqui (como o middleware `sensitive.token` da
     * API): a operação que vem depois é a única autorizada por ele.
     *
     * @throws ValidationException
     */
    private function consume(Request $request): void
    {
        $token = $this->sensitiveToken($request);

        abort_unless(app(SensitiveActionService::class)->validateToken($this->user($request), $token), 403);
    }

    private function afterStage(Request $request): RedirectResponse
    {
        $sent = $this->sensitiveStage($request);

        $redirect = to_route('panel.api-keys');

        return $sent ? $redirect->with('status', __('auth.verification_code.sent')) : $redirect;
    }

    /**
     * MESMAS regras do StoreApiKeyRequest da API v1 (e da tela Livewire).
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateKeyForm(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'all_scopes' => ['boolean'],
            'scopes' => ['array'],
            'scopes.*' => ['string', 'regex:'.StoreApiKeyRequest::SCOPE_REGEX],
            'project_uuids' => ['array'],
            'project_uuids.*' => ['uuid'],
        ], [
            'scopes.*.regex' => __('api_keys.scopes.invalid_format'),
        ], [
            'name' => __('panel.common.name'),
            'expires_at' => __('panel.api_keys.expires_at'),
        ]);

        if (! $request->boolean('all_scopes', true) && ($validated['scopes'] ?? []) === []) {
            throw ValidationException::withMessages(['scopes' => __('api_keys.scopes.invalid_format')]);
        }

        return $validated;
    }

    /**
     * Os projetos pedidos, só da conta atual (resolveProjectIds do serviço):
     * uuid de outra conta vira erro de validação, com a tentativa na trilha.
     *
     * @return list<int>
     *
     * @throws ValidationException
     */
    private function resolveProjects(Request $request, ApiKeyAttempt $attempt, ?string $keyUuid = null): array
    {
        try {
            return app(ApiKeyService::class)->resolveProjectIds(array_values((array) $request->input('project_uuids', [])));
        } catch (\InvalidArgumentException) {
            $this->guard()->foreignProjects($attempt, $keyUuid);

            throw ValidationException::withMessages(['project_uuids' => __('api_keys.projects.invalid')]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateGrace(Request $request): int
    {
        $request->validate([
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:'.(int) config('api_keys.rotation.max_grace_minutes', 10080)],
        ], [], [
            'grace_minutes' => __('panel.api_keys.rotate_title'),
        ]);

        return $request->integer('grace_minutes');
    }

    /**
     * @throws ValidationException
     */
    private function rotatableKey(string $uuid): ApiKey
    {
        $key = $this->findKey($uuid, ApiKeyAttempt::Rotated);

        if ($key->status !== ApiKeyStatus::Active || ! $key->isUsable()) {
            throw ValidationException::withMessages(['rotate' => __('api_keys.keys.not_rotatable')]);
        }

        return $key;
    }

    /**
     * Chave da CONTA ATUAL pelo uuid — de outra conta = 404 (anti-enumeração),
     * com a tentativa na trilha.
     */
    private function findKey(string $uuid, ApiKeyAttempt $attempt): ApiKey
    {
        return $this->guard()->apiKey($uuid, $attempt);
    }

    /**
     * Gerir chaves: owner ou admin da conta (403 para member, com a
     * tentativa na trilha).
     */
    private function authorizeManage(ApiKeyAttempt $attempt, ?string $keyUuid = null): void
    {
        $this->guard()->authorize(AccountAbility::ManageApiKeys, $attempt, $keyUuid);
    }

    private function guard(): AccountResourceGuard
    {
        return app(AccountResourceGuard::class);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
