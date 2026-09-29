import type { AccountRole } from '@/types/shared';

/** Um membro da conta atual e o que QUEM ESTÁ VENDO pode fazer com ele. */
export type Member = {
    uuid: string;
    name: string;
    email: string;
    avatarUrl: string | null;
    role: AccountRole;
    roleLabel: string;
    self: boolean;
    joined: string | null;
    canPromote: boolean;
    canDemote: boolean;
    canRemove: boolean;
};

/** Um convite em aberto (nunca o token nem o hash). */
export type Invitation = {
    uuid: string;
    email: string;
    role: AccountRole;
    roleLabel: string;
    status: string;
    statusLabel: string;
    pending: boolean;
    expiresAt: string;
    invitedBy: string | null;
};
