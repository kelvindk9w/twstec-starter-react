<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Twstec\Kit\Accounts\Account\Enums\AccountAbility;
use Twstec\Kit\Accounts\Account\Support\AccountResourceGuard;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\Tenancy\Enums\ProjectAttempt;
use Twstec\Kit\Accounts\Tenancy\Enums\ProjectStatus;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Accounts\Tenancy\Services\ProjectService;

/**
 * Projetos — a mesma tela do starter Livewire (App\Livewire\Projects\Index):
 * listar, criar, renomear, arquivar/reativar e excluir.
 *
 * A regra mora no ProjectService, o mesmo que a API v1 e o Livewire usam (os
 * projetos são da CONTA ATUAL: uuid de outra conta = 404). O controller
 * confere o PAPEL da pessoa na conta em cada envio (member cria e edita, não
 * exclui — AccountAbility), valida e chama o serviço.
 *
 * Toda recusa fica na trilha (AccountResourceGuard): o papel que não permite
 * (403) e o projeto que não está na conta atual (o mesmo 404 de sempre) gravam
 * `denied` com a ação tentada.
 */
final class ProjectsController
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly AccountResourceGuard $guard,
    ) {}

    public function index(): Response
    {
        return Inertia::render('projects/index', [
            'projects' => $this->projects->list()
                ->map(static fn (Project $project): array => [
                    'uuid' => (string) $project->uuid,
                    'name' => (string) $project->name,
                    'code' => (string) $project->codigo_publico,
                    'status' => $project->status->value,
                    'statusLabel' => __('panel.projects.status_'.$project->status->value),
                    'keysCount' => (int) $project->api_keys_count,
                ])
                ->values()->all(),
            'can' => [
                'create' => Accounts::can(AccountAbility::CreateProjects),
                'update' => Accounts::can(AccountAbility::UpdateProjects),
                'delete' => Accounts::can(AccountAbility::DeleteProjects),
            ],
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $this->guard->authorize(AccountAbility::CreateProjects, ProjectAttempt::Created);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']], [], [
            'name' => __('panel.common.name'),
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->projects->create($user, $validated['name']);

        return to_route('panel.projects')->with('status', __('panel.projects.created'));
    }

    /**
     * Renomear e/ou arquivar/reativar (o serviço só aceita nome e status).
     *
     * @throws ValidationException
     */
    public function update(Request $request, string $project): RedirectResponse
    {
        $this->guard->authorize(AccountAbility::UpdateProjects, ProjectAttempt::Updated, $project);
        $model = $this->guard->project($project, ProjectAttempt::Updated);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::enum(ProjectStatus::class)],
        ], [], [
            'name' => __('panel.common.name'),
            'status' => __('panel.common.status'),
        ]);

        $this->projects->update($model, $validated);

        return to_route('panel.projects')->with('status', __('panel.projects.updated'));
    }

    public function destroy(string $project): RedirectResponse
    {
        $this->guard->authorize(AccountAbility::DeleteProjects, ProjectAttempt::Deleted, $project);

        // O vínculo N:N cai junto; a chave que só atendia este projeto segue
        // restrita, agora a nenhum (ProjectService::delete()).
        $this->projects->delete($this->guard->project($project, ProjectAttempt::Deleted));

        return to_route('panel.projects')->with('status', __('panel.projects.deleted'));
    }
}
