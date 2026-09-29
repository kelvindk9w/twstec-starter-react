<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Twstec\Kit\Auth\Services\TwoFactorLogin;

/**
 * A pessoa logada, do jeito que as páginas React a veem — LISTA FECHADA.
 *
 * Nunca o model serializado: `toArray()` de um model muda sozinho quando
 * alguém acrescenta uma coluna, e uma coluna nova de segredo (ou um `$hidden`
 * esquecido) iria parar no HTML de toda página. Aqui cada campo é escolhido:
 * identificadores externos (uuid e código público — o `id` interno nunca sai),
 * nome, e-mail, idioma, tema, a foto (URL assinada, só com o pacote de
 * uploads) e três estados booleanos.
 */
final class SharedUser
{
    /**
     * @return array{uuid: string, code: string, name: string, email: string, locale: string, theme: string, avatarUrl: string|null, emailVerified: bool, hasTransactionPassword: bool, twoFactorEnabled: bool}|null
     */
    public static function from(?Authenticatable $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'uuid' => (string) $user->uuid,
            'code' => (string) $user->codigo_publico,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'locale' => $user->preferredLocale(),
            'theme' => in_array($user->theme, ['light', 'dark', 'system'], true) ? $user->theme : 'system',
            'avatarUrl' => $user->avatarUrl(),
            'emailVerified' => $user->hasVerifiedEmail(),
            'hasTransactionPassword' => $user->hasTransactionPassword(),
            'twoFactorEnabled' => app(TwoFactorLogin::class)->enabledFor($user),
        ];
    }
}
