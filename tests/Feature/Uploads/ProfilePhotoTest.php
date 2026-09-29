<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Twstec\Kit\Foundation\Kit;
use Twstec\Kit\Uploads\Models\Upload;

// =============================================================================
// FOTO DE PERFIL pelo painel React (twstec/kit-uploads): a mesma função
// global de upload seguro do Livewire (AvatarService → SecureUploadService) —
// validação pelo CONTEÚDO, reprocessamento, foto PESSOAL (sem conta), URL
// assinada nas props — e a remoção (volta às iniciais).
// =============================================================================

beforeEach(function () {
    Storage::fake('uploads-test');
    config()->set('uploads.disk', 'uploads-test');
    config()->set('security.rate_limit.sensitive', 1000);
});

it('o perfil oferece a foto só com o pacote de uploads', function () {
    $this->actingAs(User::factory()->create())->get('/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->where('photo.accept', 'image/jpeg,image/png,image/webp')
            ->where('photo.maxKb', (int) setting('uploads.types.image.max_kb')));
})->group('uploads');

it('envia uma imagem legítima: foto pessoal da pessoa, por URL assinada nas props', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withHeaders(inertiaHeaders())
        ->post('/profile/avatar', ['avatar' => fixtureArquivoEnviado(fixtureBytesPng(), 'minha-foto.png')])
        ->assertRedirect('/profile')
        ->assertSessionHas('status', __('panel.profile.avatar_updated'));

    $upload = comoSistema(fn () => Upload::query()->sole());

    expect($upload->created_by)->toBe($user->id)
        ->and($upload->personal)->toBeTrue()
        ->and($upload->account_id)->toBeNull()
        ->and($user->fresh()->avatar_upload_id)->toBe($upload->id)
        ->and($upload->path)->toStartWith('avatars/');

    Storage::disk('uploads-test')->assertExists((string) $upload->path);

    // URL temporária (no disco de teste, a do Storage::fake; no disco local
    // do kit, a rota assinada) — nunca um caminho público permanente.
    expect($this->withHeaders(inertiaHeaders())->get('/profile')->json('props.auth.user.avatarUrl'))
        ->toBe($user->fresh()->avatarUrl())
        ->toContain('expiration=');
})->group('uploads');

it('recusa pelo CONTEÚDO: PDF, texto e executável com nome de imagem — nada é gravado', function (string $bytes, string $nome) {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/profile')
        ->post('/profile/avatar', ['avatar' => fixtureArquivoEnviado($bytes, $nome)])
        ->assertRedirect('/profile')
        ->assertSessionHasErrors('avatar');

    expect(comoSistema(fn (): int => Upload::query()->count()))->toBe(0)
        ->and($user->fresh()->avatar_upload_id)->toBeNull();
})->with([
    'pdf' => [fn () => fixtureBytesPdf(), 'doc.png'],
    'texto' => ['<?php echo "oi";', 'foto.png'],
    'elf' => [fn () => fixtureBytesElf(), 'foto.jpg'],
])->group('uploads');

it('remove a foto: a pessoa volta às iniciais (a foto sai na limpeza do pacote)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/avatar', ['avatar' => fixtureArquivoEnviado(fixtureBytesPng(), 'foto.png')]);
    expect($user->fresh()->avatarUrl())->not->toBeNull();

    $this->delete('/profile/avatar')
        ->assertRedirect('/profile')
        ->assertSessionHas('status', __('ui.profile_photo.removed'));

    expect($user->fresh()->avatar_upload_id)->toBeNull()
        ->and($this->withHeaders(inertiaHeaders())->get('/profile')->json('props.auth.user.avatarUrl'))->toBeNull();
})->group('uploads');

it('exige sessão (deny-by-default)', function () {
    $this->post('/profile/avatar', ['avatar' => fixtureArquivoEnviado(fixtureBytesPng(), 'foto.png')])->assertRedirect(route('login'));
    $this->delete('/profile/avatar')->assertRedirect(route('login'));
})->group('uploads');

it('sem o pacote de uploads: sem foto no perfil e o envio responde 404', function () {
    Kit::pretendAbsent('uploads');

    $this->actingAs(User::factory()->create())->get('/profile')
        ->assertInertia(fn (Assert $page) => $page->where('photo', null));

    $this->post('/profile/avatar', ['avatar' => fixtureArquivoEnviado(fixtureBytesPng(), 'foto.png')])->assertNotFound();
    $this->delete('/profile/avatar')->assertNotFound();
});
