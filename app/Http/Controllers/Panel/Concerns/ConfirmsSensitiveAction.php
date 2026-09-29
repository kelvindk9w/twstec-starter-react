<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Services\SensitiveActionService;

/**
 * AÇÃO SENSÍVEL nas telas React do painel — o mesmo desenho do Livewire
 * (App\Livewire\Concerns\ConfirmsSensitiveAction), em envios HTTP:
 *
 *   1. `…/code` com `stage=check`: confere o PEDIDO (papel, formulário, senha
 *      de transação definida) sem mandar nada — a tela só abre a confirmação
 *      se ele passa;
 *   2. `…/code` com `stage=send` e a senha de transação: código por e-mail;
 *   3. o envio da ação com o código: o código vira o token de ação sensível
 *      (uso único) NO SERVIDOR, e a operação o consome na mesma requisição.
 *
 * O token nunca vai para o navegador; o navegador manda só a senha e o
 * código. Toda a regra (senha, código, tentativas, intervalo, token) é do
 * SensitiveActionService do pacote twstec/kit-auth.
 */
trait ConfirmsSensitiveAction
{
    /**
     * Passo 1 ou 2 — depois de o controller conferir o pedido. `true` se o
     * código foi enviado (stage=send); `false` se era só a conferência.
     *
     * @throws ValidationException
     */
    protected function sensitiveStage(Request $request): bool
    {
        if ($request->input('stage') !== 'send') {
            return false;
        }

        $request->validate(
            ['transaction_password' => ['required', 'string']],
            [],
            ['transaction_password' => __('auth.ui.transaction_password_title')],
        );

        app(SensitiveActionService::class)->sendCode($this->sensitiveUser($request), $request->string('transaction_password')->toString());

        return true;
    }

    /**
     * Passo 3: o código por e-mail vira o token de ação sensível (em claro,
     * ainda não consumido) — só para a operação desta requisição.
     *
     * @throws ValidationException
     */
    protected function sensitiveToken(Request $request): string
    {
        $request->validate(
            ['code' => ['required', 'string', 'size:6']],
            [],
            ['code' => __('panel.sensitive.code')],
        );

        return app(SensitiveActionService::class)->confirmCode($this->sensitiveUser($request), $request->string('code')->toString())['token'];
    }

    /**
     * Ação sensível pede a senha de transação DEFINIDA (a pessoa é avisada
     * antes de abrir a confirmação, no campo do formulário).
     *
     * @throws ValidationException
     */
    protected function requireTransactionPassword(Request $request, string $field, string $message): void
    {
        if (! $this->sensitiveUser($request)->hasTransactionPassword()) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /**
     * Erro da própria operação (depois do código): aparece no passo em que a
     * pessoa está (o código).
     */
    protected function asCodeError(ValidationException $exception): ValidationException
    {
        return ValidationException::withMessages(['code' => collect($exception->errors())->flatten()->all()]);
    }

    private function sensitiveUser(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
