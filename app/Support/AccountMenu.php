<?php

declare(strict_types=1);

namespace App\Support;

use Twstec\Kit\Accounts\Account\Models\AccountMembership;
use Twstec\Kit\Accounts\Account\Queries\AccountDirectory;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Foundation\Kit;

/**
 * O que o SELETOR DE CONTA do painel mostra (prop compartilhada
 * `accountMenu`): a conta atual e todas as contas da pessoa logada, cada uma
 * com o papel dela — numa consulta só (os vínculos da pessoa, com a conta),
 * a mesma leitura do starter Livewire (App\Livewire\Support\AccountMenu).
 *
 * Só identificadores externos (uuid) e textos já traduzidos. A troca é um
 * POST para a rota do pacote (accounts.switch), que só aceita conta de que a
 * pessoa é membro.
 *
 * Sem o pacote de contas (twstec/kit-accounts, opcional) não há conta: o
 * seletor não aparece (null).
 */
final class AccountMenu
{
    /**
     * @return array{current: array{uuid: string, name: string, role: string, roleLabel: string, personal: bool}, accounts: list<array{uuid: string, name: string, role: string, roleLabel: string, personal: bool, current: bool}>}|null
     */
    public static function for(?AuthUser $user): ?array
    {
        if ($user === null || ! Kit::has('accounts')) {
            return null;
        }

        $atual = Accounts::current();

        $contas = app(AccountDirectory::class)->accountsOf($user)
            ->sortBy(fn (AccountMembership $m): string => ($m->account->isPersonal() ? '0' : '1').mb_strtolower($m->account->displayName()))
            ->map(fn (AccountMembership $m): array => [
                'uuid' => (string) $m->account->uuid,
                'name' => $m->account->displayName(),
                'role' => $m->role->value,
                'roleLabel' => $m->account->isPersonal()
                    ? __('accounts.personal_account').' · '.$m->role->label()
                    : $m->role->label(),
                'personal' => $m->account->isPersonal(),
                'current' => $atual !== null && $atual->is($m->account),
            ])
            ->values()
            ->all();

        $corrente = collect($contas)->firstWhere('current', true);

        if ($corrente === null) {
            return null;
        }

        return [
            'current' => [
                'uuid' => $corrente['uuid'],
                'name' => $corrente['name'],
                'role' => $corrente['role'],
                // Como no Livewire: na conta pessoal, o selo diz "Conta pessoal".
                'roleLabel' => $corrente['personal'] ? __('accounts.personal_account') : $corrente['roleLabel'],
                'personal' => $corrente['personal'],
            ],
            'accounts' => $contas,
        ];
    }
}
