<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Panel\Concerns\ConfirmsSensitiveAction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Twstec\Kit\Webhooks\Actions\CheckEndpointInput;
use Twstec\Kit\Webhooks\Actions\CreateEndpoint;
use Twstec\Kit\Webhooks\Actions\DeleteEndpoint;
use Twstec\Kit\Webhooks\Actions\ResendDelivery;
use Twstec\Kit\Webhooks\Actions\RevealSecret;
use Twstec\Kit\Webhooks\Actions\RotateSecret;
use Twstec\Kit\Webhooks\Actions\SendTestEvent;
use Twstec\Kit\Webhooks\Actions\SetEndpointStatus;
use Twstec\Kit\Webhooks\Actions\UpdateEndpoint;
use Twstec\Kit\Webhooks\Support\WebhookAccess;
use Twstec\Kit\Webhooks\Support\WebhookPanel;

/**
 * Webhooks da conta atual — a mesma tela do starter Livewire
 * (App\Livewire\Webhooks\Index) em envios HTTP: endpoints (criar, editar,
 * ativar/desativar, excluir, enviar teste, revelar e rotacionar o segredo), o
 * log de entregas e o reenvio, tudo na mesma página.
 *
 * ZERO regra aqui: cada envio chama a Action do twstec/kit-webhooks, que
 * confere o papel (dono/admin — em CADA envio, não só no botão), o destino
 * (SSRF, DNS de agora), a ação sensível (o token nasce do código AQUI, no
 * servidor, e é consumido pela própria Action) e grava a trilha.
 *
 * O SEGREDO: só na resposta imediata da criação, da revelação ou da rotação,
 * como dado de UMA resposta do Inertia (`flash` da página, fora do histórico
 * do navegador) — nunca numa prop, na sessão ou na listagem.
 */
final class WebhooksController implements HasMiddleware
{
    use ConfirmsSensitiveAction;

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive', only: ['code', 'store', 'updateCode', 'update', 'secretCode', 'reveal', 'rotate', 'test', 'resend'])];
    }

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    /**
     * Criação, passos 1 e 2: confere o formulário e o destino (as mesmas
     * regras de quando grava) e, com `stage=send`, manda o código.
     *
     * @throws ValidationException
     */
    public function code(Request $request, CheckEndpointInput $check): RedirectResponse
    {
        $this->requireTransactionPassword($request, 'name', __('webhooks.ui.sensitive_requires_password'));
        $check->handle($this->user($request), $this->formData($request));

        return $this->afterStage($request);
    }

    /**
     * Criação, passo 3: código → token → o endpoint. A resposta é a própria
     * tela, com o segredo UMA vez.
     *
     * @throws ValidationException
     */
    public function store(Request $request, CreateEndpoint $create): Response
    {
        $token = $this->sensitiveToken($request);

        ['endpoint' => $endpoint, 'secret' => $secret] = $create->handle($this->user($request), $this->formData($request), $token);

        return $this->page($request, ['name' => $endpoint->name, 'secret' => $secret]);
    }

    /**
     * @throws ValidationException
     */
    public function updateCode(Request $request, string $endpoint, CheckEndpointInput $check): RedirectResponse
    {
        $this->requireTransactionPassword($request, 'name', __('webhooks.ui.sensitive_requires_password'));
        $check->handle($this->user($request), $this->formData($request), $endpoint);

        return $this->afterStage($request);
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request, string $endpoint, UpdateEndpoint $update): RedirectResponse
    {
        $token = $this->sensitiveToken($request);

        $update->handle($this->user($request), $endpoint, $this->formData($request), $token);

        return to_route('panel.webhooks')->with('status', __('webhooks.ui.updated'));
    }

    public function destroy(Request $request, string $endpoint, DeleteEndpoint $delete): RedirectResponse
    {
        $delete->handle($this->user($request), $endpoint);

        return to_route('panel.webhooks')->with('status', __('webhooks.ui.deleted'));
    }

    /**
     * @throws ValidationException
     */
    public function status(Request $request, string $endpoint, SetEndpointStatus $status): RedirectResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);

        $status->handle($this->user($request), $endpoint, $request->boolean('active'));

        return back()->with('status', __($request->boolean('active') ? 'webhooks.ui.enabled' : 'webhooks.ui.disabled'));
    }

    /**
     * @throws ValidationException
     */
    public function test(Request $request, string $endpoint, SendTestEvent $test): RedirectResponse
    {
        $test->handle($this->user($request), $endpoint);

        return to_route('panel.webhooks', ['endpoint' => $endpoint])->with('status', __('webhooks.ui.test_sent'));
    }

    /**
     * Revelar ou rotacionar, passos 1 e 2 (`action` = reveal | rotate; na
     * rotação, a convivência é conferida já aqui).
     *
     * @throws ValidationException
     */
    public function secretCode(Request $request, string $endpoint): RedirectResponse
    {
        $request->validate(['action' => ['required', 'in:reveal,rotate']]);
        $this->requireTransactionPassword($request, 'action', __('webhooks.ui.sensitive_requires_password'));

        abort_unless(WebhookAccess::canManage($this->user($request)), 403);

        if ($request->input('action') === 'rotate') {
            $this->validateOverlap($request);
        }

        return $this->afterStage($request);
    }

    /**
     * @throws ValidationException
     */
    public function reveal(Request $request, string $endpoint, RevealSecret $reveal): Response
    {
        $token = $this->sensitiveToken($request);
        $secret = $reveal->handle($this->user($request), $endpoint, $token);

        return $this->page($request, ['name' => $this->endpointName($endpoint), 'secret' => $secret]);
    }

    /**
     * @throws ValidationException
     */
    public function rotate(Request $request, string $endpoint, RotateSecret $rotate): Response
    {
        $overlap = $this->validateOverlap($request);
        $token = $this->sensitiveToken($request);
        $secret = $rotate->handle($this->user($request), $endpoint, $overlap, $token);

        return $this->page($request, ['name' => $this->endpointName($endpoint), 'secret' => $secret]);
    }

    /**
     * Reenvio manual de uma entrega (auditado pela Action).
     *
     * @throws ValidationException
     */
    public function resend(Request $request, string $delivery, ResendDelivery $resend): RedirectResponse
    {
        $resend->handle($this->user($request), $delivery);

        return back()->with('status', __('webhooks.ui.resent'));
    }

    // =========================================================================
    // Internos
    // =========================================================================

    /**
     * A tela. `$revealed` (o segredo) vai só nesta resposta, fora das props.
     *
     * @param  array{name: string, secret: string}|null  $revealed
     */
    private function page(Request $request, ?array $revealed = null): Response
    {
        $filter = $request->query('endpoint');
        $filter = is_string($filter) && $filter !== '' ? $filter : null;

        if ($revealed !== null) {
            // A resposta de um POST vira a tela de webhooks: recarregar não
            // repete a operação.
            Inertia::resolveUrlUsing(static fn (): string => route('panel.webhooks', absolute: false));
        }

        $response = Inertia::render('webhooks/index', [
            'endpoints' => WebhookPanel::endpoints(),
            'deliveries' => WebhookPanel::deliveries($filter),
            'deliveriesFor' => $filter,
            'eventOptions' => WebhookPanel::eventOptions(),
            'projectOptions' => WebhookPanel::projectOptions(),
            'canManage' => WebhookAccess::canManage(),
            'defaultOverlap' => (int) config('webhooks.secret.default_overlap_minutes', 1440),
            'maxOverlap' => (int) config('webhooks.secret.max_overlap_minutes', 10080),
        ]);

        if ($revealed !== null) {
            Inertia::resolveUrlUsing(null);
            $response->flash('revealedWebhookSecret', $revealed);
        }

        return $response;
    }

    private function afterStage(Request $request): RedirectResponse
    {
        $sent = $this->sensitiveStage($request);

        $redirect = to_route('panel.webhooks');

        return $sent ? $redirect->with('status', __('auth.verification_code.sent')) : $redirect;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $project = $request->input('project');

        return [
            'name' => (string) $request->input('name', ''),
            'url' => (string) $request->input('url', ''),
            'events' => array_values(array_map('strval', (array) $request->input('events', []))),
            'project' => is_string($project) && $project !== '' ? $project : null,
        ];
    }

    /**
     * @throws ValidationException
     */
    private function validateOverlap(Request $request): int
    {
        $request->validate([
            'overlap_minutes' => ['required', 'integer', 'min:0', 'max:'.(int) config('webhooks.secret.max_overlap_minutes', 10080)],
        ], [], ['overlap_minutes' => __('webhooks.ui.rotate_overlap')]);

        return $request->integer('overlap_minutes');
    }

    private function endpointName(string $uuid): string
    {
        foreach (WebhookPanel::endpoints() as $endpoint) {
            if ($endpoint['uuid'] === $uuid) {
                return (string) $endpoint['name'];
            }
        }

        return '';
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
