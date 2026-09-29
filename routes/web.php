<?php

declare(strict_types=1);

use App\Http\Controllers\Accounts\InvitationPageController;
use App\Http\Controllers\Accounts\OpenAccountController;
use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Panel\AccountController;
use App\Http\Controllers\Panel\AccountCreateController;
use App\Http\Controllers\Panel\AccountInvitationsController;
use App\Http\Controllers\Panel\AccountMembersController;
use App\Http\Controllers\Panel\ApiKeysController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\NotificationPreferencesController;
use App\Http\Controllers\Panel\PasswordController;
use App\Http\Controllers\Panel\ProfileController;
use App\Http\Controllers\Panel\ProfilePhotoController;
use App\Http\Controllers\Panel\ProjectsController;
use App\Http\Controllers\Panel\TwoFactorPreferenceController;
use App\Http\Controllers\ThemePreferenceController;
use Illuminate\Support\Facades\Route;
use Twstec\Kit\Accounts\Account\Http\Controllers\AccountSwitchController;
use Twstec\Kit\Accounts\Account\Http\Controllers\InvitationController;
use Twstec\Kit\Auth\Http\Controllers\AuthenticatedSessionController;
use Twstec\Kit\Auth\Http\Controllers\EmailVerificationController;
use Twstec\Kit\Auth\Http\Controllers\NewPasswordController;
use Twstec\Kit\Auth\Http\Controllers\PasswordResetLinkController;
use Twstec\Kit\Auth\Http\Controllers\RegisteredUserController;
use Twstec\Kit\Auth\Http\Controllers\SensitiveActionController;
use Twstec\Kit\Auth\Http\Controllers\TransactionPasswordController;
use Twstec\Kit\Auth\Http\Controllers\TwoFactorChallengeController;
use Twstec\Kit\Foundation\Kit;
use Twstec\Kit\Foundation\Localization\Http\Controllers\LocaleController;
use Twstec\Kit\Foundation\Mail\Http\Controllers\MailPreviewController;

// Página inicial do PRODUTO (mínima). Uma extensão instalada pode responder
// por "/" com a própria página: as rotas dela são carregadas antes deste
// arquivo e, nesse caso, esta não é registrada.
if (! array_key_exists('/', Route::getRoutes()->get('GET'))) {
    Route::inertia('/', 'welcome')->name('home');
}

// Troca de idioma: visitante → cookie; logado → também persiste na conta.
// Whitelist: platform()->availableLocales (fora dela = 404). O front abre o
// link com carga completa (a página volta inteira no idioma novo).
Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// Pré-visualização dos e-mails transacionais (/mail-preview) — ferramenta de
// DESENVOLVIMENTO, fechada (404) fora dela (MailPreviewGate do foundation).
Route::get('mail-preview/{slug?}', MailPreviewController::class)->name('mail.preview');

// =============================================================================
// Autenticação web (sessão) — a do pacote twstec/kit-auth, não a do kit
// oficial (sem Fortify).
//
// As TELAS (GET) são do starter (AuthPageController → páginas Inertia); o
// ENVIO de cada formulário vai para os controllers do pacote, que trazem o
// próprio `throttle:sensitive` — nenhuma rota sensível depende de alguém
// lembrar de declarar o limite aqui. As respostas são as implementações
// Inertia dos contratos do pacote (App\Http\Responses\Inertia, registradas no
// AppServiceProvider). CSRF: nativo do grupo `web`; o Inertia manda o
// X-XSRF-TOKEN a cada envio. Os endereços e os nomes são os mesmos do
// starter Livewire.
// =============================================================================

Route::middleware('guest')->group(function (): void {
    Route::get('register', [AuthPageController::class, 'register'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthPageController::class, 'login'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Segundo passo do login: quem está aqui acertou a senha, mas AINDA NÃO
    // está autenticado (só há o estado intermediário na sessão).
    Route::get('two-factor-challenge', [AuthPageController::class, 'twoFactorChallenge'])
        ->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);
    Route::post('two-factor-challenge/resend', [TwoFactorChallengeController::class, 'resend'])
        ->name('two-factor.resend');
    Route::post('two-factor-challenge/cancel', [TwoFactorChallengeController::class, 'destroy'])
        ->name('two-factor.cancel');

    Route::get('forgot-password', [AuthPageController::class, 'forgotPassword'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [AuthPageController::class, 'resetPassword'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.update');
});

// =============================================================================
// MÓDULOS OPCIONAIS. As telas de contas, chaves e projetos (twstec/kit-accounts)
// e a foto de perfil (twstec/kit-uploads) só são registradas com o pacote
// instalado — Kit::has(), o ponto único de detecção. Sem o pacote, a rota não
// existe (404), não entra no mapa de rotas do front e o menu não a mostra.
// =============================================================================

// =============================================================================
// Convite para uma conta (link do e-mail) — PÚBLICO: quem abre pode estar
// logado com o e-mail do convite, logado com outro, deslogado com conta ou
// sem conta nenhuma. A tela (GET) é do starter; os envios vão para o
// controller do pacote de contas, que traz o próprio `throttle:sensitive`.
// Aceitar exige sessão; criar a conta pelo convite exige NÃO ter sessão.
// Os mesmos endereços e nomes do starter Livewire.
// =============================================================================
if (Kit::has('accounts')) {
    Route::get('invitations/{token}', [InvitationPageController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])
        ->middleware('auth')
        ->name('invitations.accept');
    Route::post('invitations/{token}/register', [InvitationController::class, 'register'])
        ->middleware('guest')
        ->name('invitations.register');
    Route::post('invitations/{token}/decline', [InvitationController::class, 'decline'])
        ->name('invitations.decline');
}

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Preferência de tema da conta (fora do `verified`: o tema também muda
    // na tela de aviso).
    Route::post('settings/theme', ThemePreferenceController::class)->name('settings.theme');

    // Verificação de e-mail do cadastro: a saída de quem ainda não confirmou
    // — por isso fora do `verified`.
    Route::get('email/verify', [AuthPageController::class, 'verifyEmailNotice'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->name('verification.send');
    Route::get('email/verify/{uuid}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify');
});

// Tudo abaixo exige e-mail confirmado (`verified` — EnsureEmailIsVerified,
// do pacote; desligável por AUTH_EMAIL_VERIFICATION_REQUIRED).
Route::middleware(['auth', 'verified'])->group(function (): void {
    // =====================================================================
    // Painel do usuário (React + Inertia). As telas dos módulos opcionais
    // (contas, chaves, projetos, foto) ficam no fim do grupo, cada uma só
    // com o seu pacote; o menu só mostra o que existe.
    // =====================================================================
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('panel.profile');
    Route::patch('profile', [ProfileController::class, 'update'])->name('panel.profile.update');
    Route::put('profile/password', [PasswordController::class, 'update'])->name('panel.password.update');

    // Verificação em duas etapas (ação sensível, em dois envios).
    Route::post('profile/two-factor/code', [TwoFactorPreferenceController::class, 'code'])
        ->name('panel.two-factor.code');
    Route::put('profile/two-factor', [TwoFactorPreferenceController::class, 'update'])
        ->name('panel.two-factor.update');

    Route::get('notifications', [NotificationPreferencesController::class, 'edit'])->name('panel.notifications');
    Route::put('notifications', [NotificationPreferencesController::class, 'update'])->name('panel.notifications.update');

    // Senha de transação (hash separado da senha de login): a tela é do
    // starter, o envio é do pacote — o mesmo usado pelo perfil.
    Route::get('settings/transaction-password', [AuthPageController::class, 'transactionPassword'])
        ->name('transaction-password.edit');
    Route::put('settings/transaction-password', [TransactionPasswordController::class, 'update'])
        ->name('transaction-password.update');

    // Confirmação de ação sensível em JSON (para clientes próprios): senha de
    // transação + código por e-mail → token de ação sensível.
    Route::post('sensitive-actions/code', [SensitiveActionController::class, 'store'])
        ->name('sensitive-actions.code');
    Route::post('sensitive-actions/confirm', [SensitiveActionController::class, 'confirm'])
        ->name('sensitive-actions.confirm');

    if (Kit::has('accounts')) {
        // Chaves de API da conta atual. Criar e rotacionar são ações
        // sensíveis: `…/code` confere o pedido e manda o código; o envio da
        // ação traz o código (o token nasce e morre no servidor).
        Route::get('api-keys', [ApiKeysController::class, 'index'])->name('panel.api-keys');
        Route::post('api-keys/code', [ApiKeysController::class, 'code'])->name('panel.api-keys.code');
        Route::post('api-keys', [ApiKeysController::class, 'store'])->name('panel.api-keys.store');
        Route::post('api-keys/{key}/rotate/code', [ApiKeysController::class, 'rotateCode'])->name('panel.api-keys.rotate.code');
        Route::post('api-keys/{key}/rotate', [ApiKeysController::class, 'rotate'])->name('panel.api-keys.rotate');
        Route::delete('api-keys/{key}', [ApiKeysController::class, 'revoke'])->name('panel.api-keys.revoke');
        Route::put('api-keys/{key}/projects', [ApiKeysController::class, 'projects'])->name('panel.api-keys.projects');

        // Projetos da conta atual (ProjectService).
        Route::get('projects', [ProjectsController::class, 'index'])->name('panel.projects');
        Route::post('projects', [ProjectsController::class, 'store'])->name('panel.projects.store');
        Route::patch('projects/{project}', [ProjectsController::class, 'update'])->name('panel.projects.update');
        Route::delete('projects/{project}', [ProjectsController::class, 'destroy'])->name('panel.projects.destroy');

        // A página da conta atual (dados, membros, convites, transferência,
        // exclusão) e a criação de uma conta de empresa. Toda mudança passa
        // por uma Action do pacote (papel, trilha, recusas).
        Route::get('account', [AccountController::class, 'show'])->name('panel.account');
        Route::patch('account', [AccountController::class, 'update'])->name('panel.account.update');
        Route::post('account/leave', [AccountController::class, 'leave'])->name('panel.account.leave');
        Route::post('account/transfer/code', [AccountController::class, 'transferCode'])->name('panel.account.transfer.code');
        Route::post('account/transfer', [AccountController::class, 'transfer'])->name('panel.account.transfer');
        Route::post('account/delete/code', [AccountController::class, 'deleteCode'])->name('panel.account.delete.code');
        Route::delete('account', [AccountController::class, 'destroy'])->name('panel.account.destroy');
        Route::patch('account/members/{member}', [AccountMembersController::class, 'update'])->name('panel.account.members.update');
        Route::delete('account/members/{member}', [AccountMembersController::class, 'destroy'])->name('panel.account.members.destroy');
        Route::post('account/invitations', [AccountInvitationsController::class, 'store'])->name('panel.account.invitations.store');
        Route::post('account/invitations/{invitation}/resend', [AccountInvitationsController::class, 'resend'])->name('panel.account.invitations.resend');
        Route::delete('account/invitations/{invitation}', [AccountInvitationsController::class, 'destroy'])->name('panel.account.invitations.revoke');
        Route::get('accounts/create', [AccountCreateController::class, 'create'])->name('panel.accounts.create');
        Route::post('accounts', [AccountCreateController::class, 'store'])->name('panel.accounts.store');

        // Troca de conta (o seletor): POST para o controller do pacote — só
        // conta de que a pessoa é membro (senão 403 e `denied` na trilha).
        Route::post('accounts/{account}/switch', [AccountSwitchController::class, 'store'])->name('accounts.switch');

        // Link dos e-mails de conta: abre uma tela já na conta certa. Só com
        // URL ASSINADA (ninguém monta um link que troca a conta de outra
        // pessoa).
        Route::get('accounts/{account}/open/{to}', OpenAccountController::class)
            ->middleware('signed:relative')
            ->name('accounts.open');
    }

    // Foto de perfil: a função global de upload seguro do kit, só imagem.
    if (Kit::has('uploads')) {
        Route::post('profile/avatar', [ProfilePhotoController::class, 'update'])->name('panel.avatar.update');
        Route::delete('profile/avatar', [ProfilePhotoController::class, 'destroy'])->name('panel.avatar.destroy');
    }
});
