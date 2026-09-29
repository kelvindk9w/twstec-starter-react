<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\PasswordPolicy;

/**
 * Troca da senha de LOGIN no perfil: exige a senha atual e aplica a mesma
 * política do cadastro (PasswordPolicy) — as regras, os rótulos e as
 * mensagens do starter Livewire (Profile::updatePassword). Senha errada é
 * tentativa de adivinhação: o envio passa pelo `throttle:sensitive`.
 */
final class PasswordController implements HasMiddleware
{
    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive')];
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', PasswordPolicy::rule()],
            'password_confirmation' => ['required', 'same:password'],
        ], [], [
            'current_password' => __('panel.profile.current_password'),
            'password' => __('auth.ui.new_password'),
            'password_confirmation' => __('auth.ui.password_confirmation'),
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($validated['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('panel.profile.current_password_invalid'),
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        return back()->with('status', __('panel.profile.password_updated'));
    }
}
