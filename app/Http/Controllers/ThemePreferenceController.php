<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Preferência de tema do usuário logado (claro/escuro/sistema).
 *
 * O dispositivo resolve na hora (localStorage `theme` + o script do <head>,
 * sem flash de tema errado — ver resources/views/app.blade.php); esta rota só
 * persiste o padrão da CONTA, usado em outros dispositivos e sessões. Uma
 * visita do Inertia volta à tela (sem recarregar nada além das props); uma
 * chamada JSON recebe o valor gravado, como no starter Livewire.
 */
final class ThemePreferenceController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);

        $request->user()->forceFill(['theme' => $validated['theme']])->save();

        if ($request->header('X-Inertia') !== null) {
            return back();
        }

        return response()->json(['theme' => $validated['theme']]);
    }
}
