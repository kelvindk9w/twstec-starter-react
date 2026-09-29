<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Services\SensitiveActionService;
use Twstec\Kit\Auth\Services\TwoFactorLogin;

/**
 * Ligar/desligar a verificação em duas etapas do login — AÇÃO SENSÍVEL:
 * senha de transação → código por e-mail → token de uso único → operação.
 *
 * O mesmo desenho do Livewire (ConfirmsSensitiveAction): o token de ação
 * sensível é emitido e consumido NO SERVIDOR, na mesma requisição que recebe
 * o código — ele nunca vai para o navegador. (As rotas JSON
 * `sensitive-actions.*` do pacote continuam disponíveis para quem precisa do
 * token num cliente próprio.) Quem confere o token é o próprio
 * TwoFactorLogin: nenhum caminho troca a preferência sem ele.
 *
 * Toda a regra (senha, código, tentativas, intervalo, token) é do pacote
 * twstec/kit-auth; aqui há só o HTTP. `throttle:sensitive` nos dois envios.
 */
final class TwoFactorPreferenceController implements HasMiddleware
{
    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive')];
    }

    /**
     * Passo 1: senha de transação → código por e-mail. Conta que não pode
     * (protegida, sem senha de transação, opção desligada) recebe o motivo.
     *
     * @throws ValidationException
     */
    public function code(Request $request, TwoFactorLogin $twoFactor, SensitiveActionService $sensitive): RedirectResponse
    {
        $user = $this->user($request);

        $reason = $twoFactor->blockedReason($user);

        if ($reason !== null) {
            throw ValidationException::withMessages(['two_factor' => $reason]);
        }

        $request->validate(
            ['transaction_password' => ['required', 'string']],
            [],
            ['transaction_password' => __('auth.ui.transaction_password_title')],
        );

        $sensitive->sendCode($user, $request->string('transaction_password')->toString());

        return back()->with('status', __('auth.verification_code.sent'));
    }

    /**
     * Passo 2: código → token (uso único) → liga ou desliga. `enabled` é o
     * estado PEDIDO: se já é o atual, nada muda.
     *
     * @throws ValidationException
     */
    public function update(Request $request, TwoFactorLogin $twoFactor, SensitiveActionService $sensitive): RedirectResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'enabled' => ['required', 'boolean'],
        ], [], [
            'code' => __('panel.sensitive.code'),
        ]);

        $enable = (bool) $validated['enabled'];

        $issued = $sensitive->confirmCode($user, $validated['code']);

        try {
            $enable
                ? $twoFactor->enable($user, $issued['token'])
                : $twoFactor->disable($user, $issued['token']);
        } catch (ValidationException $exception) {
            // Recusa da própria operação: aparece no passo em que a pessoa está.
            throw ValidationException::withMessages(['code' => collect($exception->errors())->flatten()->all()]);
        }

        return back()->with('status', __($enable ? 'auth.two_factor.enabled' : 'auth.two_factor.disabled'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
