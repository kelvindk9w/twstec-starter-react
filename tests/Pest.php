<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EnforcedCsrfToken;
use Tests\TestCase;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;

// Arranjo das telas de contas e os arquivos de teste de upload (bytes reais).
require_once __DIR__.'/Support/Accounts.php';
require_once __DIR__.'/Fixtures/uploads.php';

// Pest 4. Feature: a aplicação Laravel completa + banco de teste — SQLite em
// memória no phpunit.xml (padrão local) ou PostgreSQL no phpunit.pgsql.xml.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
 * Visita de uma página pelo Inertia (XHR), como o front React faz depois da
 * primeira carga: cabeçalhos X-Inertia e a versão dos assets.
 *
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ];
}

/*
 * O último código enviado por e-mail com uma finalidade (Mail::fake).
 */
function lastVerificationCode(VerificationPurpose $purpose): string
{
    $mails = Mail::queued(VerificationCodeMail::class)
        ->filter(fn (VerificationCodeMail $mail): bool => $mail->purpose === $purpose);

    expect($mails)->not->toBeEmpty();

    return $mails->last()->code;
}

/*
 * Dublê de teste: reativa a verificação CSRF, que o framework desliga em
 * ambiente de teste (PreventRequestForgery::runningUnitTests()).
 */
function enforceCsrf(): void
{
    app()->bind(
        PreventRequestForgery::class,
        EnforcedCsrfToken::class,
    );
}
