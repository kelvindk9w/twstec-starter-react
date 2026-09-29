<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Panel\Concerns\ConfirmsSensitiveAction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Twstec\Kit\Accounts\Account\Actions\DeleteAccount;
use Twstec\Kit\Accounts\Account\Actions\LeaveAccount;
use Twstec\Kit\Accounts\Account\Actions\RenameAccount;
use Twstec\Kit\Accounts\Account\Actions\TransferOwnership;
use Twstec\Kit\Accounts\Account\Enums\AccountAbility;
use Twstec\Kit\Accounts\Account\Enums\AccountRole;
use Twstec\Kit\Accounts\Account\Enums\InvitationStatus;
use Twstec\Kit\Accounts\Account\Models\Account;
use Twstec\Kit\Accounts\Account\Models\AccountInvitation;
use Twstec\Kit\Accounts\Account\Queries\AccountDirectory;
use Twstec\Kit\Accounts\Account\Support\MemberRules;
use Twstec\Kit\Accounts\Accounts;

/**
 * A PÁGINA DA CONTA ATUAL — dados, membros, convites e os fluxos de dono.
 * A mesma tela do starter Livewire (App\Livewire\Account\Show), em envios
 * HTTP; os membros e os convites têm controllers próprios
 * (AccountMembersController, AccountInvitationsController).
 *
 * A REGRA mora nas Actions do pacote de contas: cada uma confere o papel,
 * grava a trilha de auditoria (inclusive as recusas, como `denied`) e
 * responde 403 ao que o papel não permite — também na PRÉ-CHECAGEM
 * (`authorize()`) que esta tela faz antes de mandar o código. A tela só ESCONDE o que o papel
 * não permite, pela mesma regra (MemberRules / AccountAbility), e nenhum
 * envio daqui grava a trilha por conta própria.
 *
 * Transferir e excluir são AÇÕES SENSÍVEIS: senha de transação → código por
 * e-mail → token de uso único, que a Action consome.
 */
final class AccountController implements HasMiddleware
{
    use ConfirmsSensitiveAction;

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive', only: ['transferCode', 'transfer', 'deleteCode', 'destroy'])];
    }

    public function show(Request $request, AccountDirectory $directory): Response
    {
        $account = Accounts::currentOrFail();
        $user = $this->user($request);
        $papel = Accounts::roleOf($user);
        $canManage = Accounts::can(AccountAbility::ManageMembers);

        return Inertia::render('account/show', [
            'account' => [
                'uuid' => (string) $account->uuid,
                'name' => $account->displayName(),
                'code' => (string) $account->codigo_publico,
                'personal' => $account->isPersonal(),
                'typeLabel' => $account->isPersonal() ? __('accounts.personal_account') : __('panel.account.type_company'),
            ],
            'role' => $papel === null ? null : ['value' => $papel->value, 'label' => $papel->label()],
            'members' => $this->members($directory, $account, $user, $papel),
            'invitations' => $canManage ? $this->invitations($directory) : [],
            'roleOptions' => array_map(static fn (AccountRole $r): array => ['value' => $r->value, 'label' => $r->label()], [AccountRole::Member, AccountRole::Admin]),
            'can' => [
                'rename' => ! $account->isPersonal() && Accounts::can(AccountAbility::UpdateAccount),
                'invite' => $canManage,
                'transfer' => ! $account->isPersonal() && Accounts::can(AccountAbility::TransferOwnership),
                'delete' => ! $account->isPersonal() && Accounts::can(AccountAbility::DeleteAccount),
                'leave' => MemberRules::canLeave($papel),
            ],
        ]);
    }

    /**
     * Renomear a conta (dono e admin; a pessoal não tem nome próprio).
     *
     * @throws ValidationException
     */
    public function update(Request $request, RenameAccount $rename): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']], [], ['name' => __('panel.common.name')]);

        $rename->handle($this->user($request), $validated['name']);

        return to_route('panel.account')->with('status', __('panel.account.renamed'));
    }

    /**
     * Sair da conta (quem não é o dono). Volta ao painel, na conta pessoal.
     */
    public function leave(Request $request, LeaveAccount $leave): RedirectResponse
    {
        $nome = Accounts::currentOrFail()->displayName();

        $leave->handle($this->user($request));

        return to_route('dashboard')->with('status', __('panel.account.left', ['account' => $nome]));
    }

    /**
     * Transferir, passos 1 e 2: só o dono, para um membro escolhido, com a
     * senha de transação definida.
     *
     * @throws ValidationException
     */
    public function transferCode(Request $request, TransferOwnership $transfer): RedirectResponse
    {
        $transfer->authorize($this->user($request));
        $request->validate(['transfer_to' => ['required', 'uuid']], [], ['transfer_to' => __('panel.account.transfer_to')]);
        $this->requireTransactionPassword($request, 'transfer_to', __('panel.account.sensitive_requires_password'));

        return $this->afterStage($request);
    }

    /**
     * Transferir, passo 3: a Action confere o papel, o membro e CONSOME o
     * token (sem token válido, nada muda: 403 e `denied` na trilha).
     *
     * @throws ValidationException
     */
    public function transfer(Request $request, TransferOwnership $transfer): RedirectResponse
    {
        // O papel antes do código (quem não pode nem gasta o código), pela
        // pré-checagem da própria Action: a recusa fica na trilha (`denied`).
        // handle() confere de novo.
        $transfer->authorize($this->user($request));
        $request->validate(['transfer_to' => ['required', 'uuid']], [], ['transfer_to' => __('panel.account.transfer_to')]);

        $token = $this->sensitiveToken($request);

        try {
            $transfer->handle($this->user($request), $request->string('transfer_to')->toString(), $token);
        } catch (ValidationException $exception) {
            throw $this->asCodeError($exception);
        }

        return to_route('panel.account')->with('status', __('panel.account.transferred'));
    }

    /**
     * Excluir, passos 1 e 2: só o dono, com a senha de transação definida.
     *
     * @throws ValidationException
     */
    public function deleteCode(Request $request, DeleteAccount $delete): RedirectResponse
    {
        $delete->authorize($this->user($request));
        $this->requireTransactionPassword($request, 'delete_account', __('panel.account.sensitive_requires_password'));

        return $this->afterStage($request);
    }

    /**
     * Excluir, passo 3: a conta sai com projetos, chaves e convites; a pessoa
     * volta à conta pessoal.
     *
     * @throws ValidationException
     */
    public function destroy(Request $request, DeleteAccount $delete): RedirectResponse
    {
        $delete->authorize($this->user($request));
        $nome = Accounts::currentOrFail()->displayName();

        $token = $this->sensitiveToken($request);

        try {
            $delete->handle($this->user($request), $token);
        } catch (ValidationException $exception) {
            throw $this->asCodeError($exception);
        }

        return to_route('dashboard')->with('status', __('panel.account.deleted', ['account' => $nome]));
    }

    // =========================================================================
    // Internos
    // =========================================================================

    private function afterStage(Request $request): RedirectResponse
    {
        $sent = $this->sensitiveStage($request);

        $redirect = to_route('panel.account');

        return $sent ? $redirect->with('status', __('auth.verification_code.sent')) : $redirect;
    }

    /**
     * Membros da conta atual, com o papel e o que QUEM ESTÁ VENDO pode fazer
     * com cada um (a mesma regra que a Action aplica).
     *
     * @return list<array<string, mixed>>
     */
    private function members(AccountDirectory $directory, Account $account, User $viewer, ?AccountRole $papel): array
    {
        $ordem = [AccountRole::Owner->value => 0, AccountRole::Admin->value => 1, AccountRole::Member->value => 2];

        return $directory->members($account)
            ->map(static function (User $pessoa) use ($viewer, $papel): array {
                $role = AccountRole::from((string) $pessoa->pivot->getAttribute('role'));
                $self = $pessoa->is($viewer);
                $joined = $pessoa->pivot->getAttribute('created_at');

                return [
                    'uuid' => (string) $pessoa->uuid,
                    'name' => (string) $pessoa->name,
                    'email' => (string) $pessoa->email,
                    'avatarUrl' => $pessoa->avatarUrl(),
                    'role' => $role->value,
                    'roleLabel' => $role->label(),
                    'self' => $self,
                    'joined' => $joined === null ? null : Carbon::parse($joined)->translatedFormat('d M Y'),
                    'canPromote' => MemberRules::canChangeRole($papel, $role, AccountRole::Admin, $self),
                    'canDemote' => MemberRules::canChangeRole($papel, $role, AccountRole::Member, $self),
                    'canRemove' => MemberRules::canRemove($papel, $role, $self),
                ];
            })
            ->sortBy(static fn (array $m): string => $ordem[$m['role']].mb_strtolower($m['name']))
            ->values()
            ->all();
    }

    /**
     * Convites em aberto (pendentes e expirados). Nunca o token nem o hash.
     *
     * @return list<array<string, mixed>>
     */
    private function invitations(AccountDirectory $directory): array
    {
        return $directory->openInvitations()
            ->map(static function (AccountInvitation $invitation): array {
                $status = $invitation->status();

                return [
                    'uuid' => (string) $invitation->uuid,
                    'email' => (string) $invitation->email,
                    'role' => $invitation->role->value,
                    'roleLabel' => $invitation->role->label(),
                    'status' => $status->value,
                    'statusLabel' => $status->label(),
                    'pending' => $status === InvitationStatus::Pending,
                    'expiresAt' => $invitation->expires_at->translatedFormat('d M Y H:i'),
                    'invitedBy' => $invitation->creator?->getAttribute('name'),
                ];
            })
            ->values()
            ->all();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
