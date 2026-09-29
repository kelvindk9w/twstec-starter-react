<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Foundation\Kit;
use Twstec\Kit\Uploads\Avatar\AvatarService;
use Twstec\Kit\Uploads\Exceptions\UploadRejectedException;
use Twstec\Kit\Uploads\Rules\SafeFile;

/**
 * FOTO DE PERFIL (twstec/kit-uploads, opcional): enviar e tirar.
 *
 * O envio passa pela mesma função global de upload seguro do kit
 * (AvatarService → SecureUploadService): o arquivo é validado pelo CONTEÚDO
 * (SafeFile, primeiro — é ele quem explica a recusa) e reprocessado antes de
 * ser gravado; a foto é da PESSOA (upload pessoal, sem conta) e aparece em
 * todas as contas dela, por URL assinada. As mesmas regras do perfil do
 * starter Livewire.
 *
 * Tirar a foto só desfaz o vínculo; o arquivo sai na limpeza do pacote.
 * Sem o pacote, as rotas nem existem (e o serviço só é pedido ao container
 * depois dessa pergunta).
 */
final class ProfilePhotoController implements HasMiddleware
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
        abort_unless(Kit::has('uploads'), 404);

        $maxKb = (int) setting('uploads.types.image.max_kb');

        $request->validate([
            'avatar' => ['bail', 'required', 'file', new SafeFile(['image']), 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ], [], [
            'avatar' => __('panel.profile.avatar_heading'),
        ]);

        try {
            app(AvatarService::class)->replace($this->user($request), $request->file('avatar'));
        } catch (UploadRejectedException $exception) {
            throw ValidationException::withMessages(['avatar' => $exception->getMessage()]);
        }

        return to_route('panel.profile')->with('status', __('panel.profile.avatar_updated'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(Kit::has('uploads'), 404);

        app(AvatarService::class)->remove($this->user($request));

        return to_route('panel.profile')->with('status', __('ui.profile_photo.removed'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
