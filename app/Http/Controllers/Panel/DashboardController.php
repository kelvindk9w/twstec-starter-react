<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Twstec\Kit\Accounts\Tenancy\Queries\AccountOverviewQuery;
use Twstec\Kit\Foundation\Kit;
use Twstec\Kit\Foundation\Logging\Enums\RequestLogStatus;
use Twstec\Kit\Foundation\Logging\Models\RequestLog;

/**
 * Painel inicial: os números da CONTA ATUAL (AccountOverviewQuery, do pacote
 * de contas — o mesmo do starter Livewire) quando o módulo está instalado;
 * sem ele, a página abre com os atalhos da conta da pessoa.
 *
 * Só dados de exibição vão para a página: contagens, a série diária e as
 * últimas chamadas da API (método, caminho, status, quando) — nada de
 * cabeçalho, corpo ou IP da trilha.
 */
final class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'overview' => Kit::has('accounts') ? $this->overview() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(): array
    {
        $overview = app(AccountOverviewQuery::class)->forCurrentAccount();
        $timezone = platform()->displayTimezone;

        return [
            'activeKeysCount' => $overview->activeKeysCount,
            'projectsCount' => $overview->projectsCount,
            'recentRequestsCount' => $overview->recentRequestsCount,
            'recentRequestsDays' => $overview->recentRequestsDays,
            'lastKeyUsedAt' => $overview->lastKeyUsedAt === null ? null : [
                'at' => Carbon::parse($overview->lastKeyUsedAt)->setTimezone($timezone)->format('d/m H:i'),
                'ago' => Carbon::parse($overview->lastKeyUsedAt)->diffForHumans(),
            ],
            'chartDays' => $overview->chartDays,
            'chart' => $overview->chart,
            'recentCalls' => $overview->recentCalls->map(static fn (RequestLog $call): array => [
                'uuid' => (string) $call->uuid,
                'method' => (string) $call->method,
                'endpoint' => (string) $call->endpoint,
                'status' => $call->http_status_response ?? $call->status->value,
                'tone' => match (true) {
                    $call->status === RequestLogStatus::Bloqueada, $call->status === RequestLogStatus::Erro => 'error',
                    $call->status === RequestLogStatus::Iniciada => 'warning',
                    ($call->http_status_response ?? 200) >= 400 => 'warning',
                    default => 'success',
                },
                'when' => $call->created_at?->setTimezone($timezone)->format('d/m/Y H:i'),
            ])->values()->all(),
        ];
    }
}
