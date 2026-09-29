<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Twstec\Kit\Auth\PasswordPolicy;
use Twstec\Kit\Auth\Services\TwoFactorLogin;
use Twstec\Kit\Foundation\Kit;

/**
 * Perfil: dados (nome, idioma; o e-mail é só leitura), aparência, senha de
 * login, senha de transação e verificação em duas etapas — a mesma tela do
 * starter Livewire (App\Livewire\Profile), com as mesmas regras e mensagens.
 *
 * Cada bloco envia para a própria rota: dados aqui; senha de login no
 * PasswordController; senha de transação no controller do pacote
 * twstec/kit-auth (a MESMA regra do Livewire, sem cópia); segundo fator no
 * TwoFactorPreferenceController; foto de perfil (twstec/kit-uploads,
 * opcional) no ProfilePhotoController.
 */
final class ProfileController
{
    public function edit(Request $request, TwoFactorLogin $twoFactor): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'passwordHint' => PasswordPolicy::hint(),
            'transactionPasswordMinLength' => (int) config('auth.transaction_password.min_length', 8),
            // Foto de perfil: só com o pacote de uploads (sem ele, as iniciais).
            'photo' => Kit::has('uploads') ? [
                'maxKb' => (int) setting('uploads.types.image.max_kb'),
                'accept' => 'image/jpeg,image/png,image/webp',
            ] : null,
            'twoFactor' => [
                'available' => TwoFactorLogin::available(),
                'enabled' => $twoFactor->enabledFor($user),
                'blockedReason' => $twoFactor->blockedReason($user),
            ],
        ]);
    }

    /**
     * Dados básicos (nome, idioma). O e-mail é a chave de acesso da conta —
     * a troca exige fluxo próprio de verificação (futuro), como no Livewire.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', Rule::in(platform()->availableLocales)],
        ], [], [
            'name' => __('panel.common.name'),
            'locale' => __('panel.profile.locale_label'),
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['name' => $validated['name'], 'locale' => $validated['locale']])->save();

        // A mensagem já sai no idioma novo (o SetLocale garante as próximas
        // requisições; as traduções do front vêm pela chave do idioma).
        app()->setLocale($validated['locale']);

        return back()->with('status', __('panel.common.saved'));
    }
}
