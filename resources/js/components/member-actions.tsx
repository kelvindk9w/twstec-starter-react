import { ArrowDownCircle, ArrowUpCircle, UserMinus } from 'lucide-react';
import { IconAction } from '@/components/icon-action';
import { useTrans } from '@/lib/i18n';
import type { Member } from '@/types/account';

/**
 * As ações sobre um membro que QUEM ESTÁ VENDO pode fazer (a regra do pacote,
 * MemberRules, calculada no servidor): promover (verde), rebaixar (âmbar),
 * remover (vermelho). O que o papel não permite não aparece — e o servidor
 * recusa do mesmo jeito.
 */
export function MemberActions({
    member,
    onChangeRole,
    onRemove,
}: {
    member: Member;
    onChangeRole: (member: Member, role: 'admin' | 'member') => void;
    onRemove: (member: Member) => void;
}) {
    const { t } = useTrans();

    if (!member.canPromote && !member.canDemote && !member.canRemove) {
        return null;
    }

    return (
        <span
            className="flex items-center justify-end gap-1"
            data-member-actions
        >
            {member.canPromote && (
                <IconAction
                    icon={ArrowUpCircle}
                    tone="green"
                    label={t('panel.account.promote')}
                    onClick={() => onChangeRole(member, 'admin')}
                    data-action="promote"
                />
            )}
            {member.canDemote && (
                <IconAction
                    icon={ArrowDownCircle}
                    tone="amber"
                    label={t('panel.account.demote')}
                    onClick={() => onChangeRole(member, 'member')}
                    data-action="demote"
                />
            )}
            {member.canRemove && (
                <IconAction
                    icon={UserMinus}
                    tone="red"
                    label={t('panel.account.remove')}
                    onClick={() => onRemove(member)}
                    data-action="remove"
                />
            )}
        </span>
    );
}
