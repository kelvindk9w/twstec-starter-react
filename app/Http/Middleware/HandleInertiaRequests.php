<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AccountMenu;
use App\Support\FrontRoutes;
use App\Support\FrontTranslations;
use App\Support\Navigation;
use App\Support\SharedUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Support\EmailVerification;
use Twstec\Kit\Foundation\Kit;

/**
 * O que TODA página React recebe (props compartilhadas do Inertia).
 *
 * REGRA: nada de credencial. As props vão para o navegador em JSON — no
 * primeiro carregamento, dentro do HTML. Por isso o usuário logado NÃO é o
 * model serializado (o kit oficial manda `$request->user()` inteiro): é a
 * lista fechada de SharedUser, sem id interno, hash de senha, senha de
 * transação, token de "manter conectado" nem nada do segundo fator além de
 * "ligado ou não". Nenhuma chave de API, código, token ou pepper passa por
 * aqui; um teste varre as props de todas as telas atrás disso
 * (tests/Feature/Inertia/SharedPropsTest.php).
 *
 * As traduções vão UMA vez por idioma (once prop com a chave do idioma): a
 * troca de idioma recarrega a página e traz o conjunto novo.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),

            'app' => fn (): array => [
                'name' => platform()->name,
                'logoUrl' => platform()->logoUrl,
                'locale' => $locale,
                'locales' => array_map(static fn (string $code): array => [
                    'code' => $code,
                    'label' => __("ui.locale.names.{$code}"),
                    'url' => route('locale.switch', ['locale' => $code], absolute: false),
                ], platform()->availableLocales),
            ],

            'auth' => fn (): array => [
                'user' => SharedUser::from($request->user()),
            ],

            // Módulos opcionais instalados (Kit::has — o mesmo ponto único do
            // backend). Sem o módulo, a página não oferece a tela dele: a
            // rota nem existe.
            'kit' => fn (): array => [
                'modules' => array_combine(Kit::OPTIONAL, array_map(Kit::has(...), Kit::OPTIONAL)),
            ],

            // Menu lateral do painel — só para quem já entra no painel.
            'navigation' => fn (): array => $this->canSeePanel($request) ? Navigation::panel($request) : [],

            // Seletor de conta (twstec/kit-accounts): a conta atual e as contas
            // da pessoa, com o papel em cada. Sem o módulo (ou fora do
            // painel), null — o seletor não aparece.
            'accountMenu' => fn (): ?array => $this->canSeePanel($request) ? AccountMenu::for($request->user()) : null,

            // Endereços das rotas que o front usa, pelo NOME (nada de URL
            // escrita no TypeScript). Só as que existem nesta instalação.
            'routes' => fn (): array => FrontRoutes::all(),

            // Mensagens de uma requisição só (as respostas do pacote de
            // autenticação usam a sessão: `status`, `verification_error`).
            'flash' => fn (): array => [
                'status' => $request->session()->get('status'),
                'verification_error' => $request->session()->get('verification_error'),
            ],

            'translations' => Inertia::once(fn (): array => FrontTranslations::for($locale))->as("translations.{$locale}"),

            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    private function canSeePanel(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof AuthUser && ! EmailVerification::pendingFor($user);
    }
}
