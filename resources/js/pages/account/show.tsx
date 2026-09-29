import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftRight, LogOut, Pencil, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { IconAction } from '@/components/icon-action';
import InputError from '@/components/input-error';
import { InvitationsList } from '@/components/invitations-list';
import { MembersList } from '@/components/members-list';
import { NameDialog } from '@/components/name-dialog';
import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { SectionCard } from '@/components/section-card';
import {
    checkSensitiveAction,
    SensitiveActionDialog,
} from '@/components/sensitive-action-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { ViewToggle } from '@/components/view-toggle';
import { useViewMode } from '@/hooks/use-view-mode';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';
import type { AccountRole } from '@/types';
import type { Invitation, Member } from '@/types/account';

type Props = {
    account: {
        uuid: string;
        name: string;
        code: string;
        personal: boolean;
        typeLabel: string;
    };
    role: { value: AccountRole; label: string } | null;
    members: Member[];
    invitations: Invitation[];
    roleOptions: { value: 'member' | 'admin'; label: string }[];
    can: {
        rename: boolean;
        invite: boolean;
        transfer: boolean;
        delete: boolean;
        leave: boolean;
    };
};

/**
 * A PÁGINA DA CONTA ATUAL — dados, membros (tabela ou cartões), convites e os
 * fluxos do dono; a mesma do starter Livewire. Cada ação aparece só para o
 * papel que pode fazê-la (a regra do pacote, calculada no servidor) — e a
 * Action do pacote recusa (403, com a tentativa na trilha) o que vier por
 * fora. Transferir e excluir pedem a confirmação de segurança.
 */
export default function AccountShow({
    account,
    role,
    members,
    invitations,
    roleOptions,
    can,
}: Props) {
    const { t, tc } = useTrans();
    const { route } = useRoute();
    const { auth, errors } = usePage().props;
    const [membersMode, setMembersMode] = useViewMode('members');
    const [invitationsMode, setInvitationsMode] = useViewMode('invitations');

    const [renaming, setRenaming] = useState(false);
    const [removing, setRemoving] = useState<Member | null>(null);
    const [revoking, setRevoking] = useState<Invitation | null>(null);
    const [leaving, setLeaving] = useState(false);
    const [transferTo, setTransferTo] = useState('');
    const [sensitive, setSensitive] = useState<'transfer' | 'delete' | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const invite = useForm({ email: '', role: 'member' });

    const hasTransactionPassword = auth.user?.hasTransactionPassword ?? false;
    const candidates = members.filter((m) => m.role !== 'owner');
    const transferName = members.find((m) => m.uuid === transferTo)?.name ?? '';

    const busy = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
    };

    const changeRole = (member: Member, next: 'admin' | 'member') =>
        router.patch(
            route('panel.account.members.update', { member: member.uuid }),
            { role: next },
            { preserveScroll: true },
        );

    const remove = () =>
        removing &&
        router.delete(
            route('panel.account.members.destroy', { member: removing.uuid }),
            {
                ...busy,
                onFinish: () => {
                    setProcessing(false);
                    setRemoving(null);
                },
            },
        );

    const resend = (invitation: Invitation) =>
        router.post(
            route('panel.account.invitations.resend', {
                invitation: invitation.uuid,
            }),
            {},
            { preserveScroll: true },
        );

    const revoke = () =>
        revoking &&
        router.delete(
            route('panel.account.invitations.revoke', {
                invitation: revoking.uuid,
            }),
            {
                ...busy,
                onFinish: () => {
                    setProcessing(false);
                    setRevoking(null);
                },
            },
        );

    const leave = () => router.post(route('panel.account.leave'), {}, busy);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <PageHeader
                    title={t('panel.account.title')}
                    description={t('panel.account.subtitle')}
                />
                {role && (
                    <RoleBadge
                        role={role.value}
                        label={t('panel.account.your_role', {
                            role: role.label,
                        })}
                        data-my-role
                    />
                )}
            </div>

            {/* Dados da conta */}
            <SectionCard title={t('panel.account.details')}>
                <div className="flex items-start gap-3">
                    <dl className="grid min-w-0 flex-1 gap-4 text-sm sm:grid-cols-3">
                        <div className="min-w-0">
                            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {t('panel.common.name')}
                            </dt>
                            <dd
                                className="mt-1 truncate font-medium"
                                data-account-name
                            >
                                {account.name}
                            </dd>
                        </div>
                        <div className="min-w-0">
                            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {t('panel.account.code')}
                            </dt>
                            <dd className="mt-1">
                                <code className="font-mono text-xs">
                                    {account.code}
                                </code>
                            </dd>
                        </div>
                        <div className="min-w-0">
                            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {t('panel.account.type')}
                            </dt>
                            <dd className="mt-1">{account.typeLabel}</dd>
                        </div>
                    </dl>
                    {can.rename && (
                        <IconAction
                            icon={Pencil}
                            tone="blue"
                            label={t('panel.account.rename')}
                            onClick={() => setRenaming(true)}
                            data-action="rename"
                        />
                    )}
                </div>
                {account.personal && (
                    <p className="mt-4 text-xs text-muted-foreground">
                        {t('panel.account.personal_hint')}
                    </p>
                )}
            </SectionCard>

            {/* Membros */}
            <section className="space-y-3" data-members>
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {t('panel.account.members')}
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            {tc('panel.account.members_count', members.length)}
                        </p>
                    </div>
                    <ViewToggle value={membersMode} onChange={setMembersMode} />
                </div>
                <MembersList
                    members={members}
                    mode={membersMode}
                    onChangeRole={changeRole}
                    onRemove={setRemoving}
                />
            </section>

            {can.invite && (
                <>
                    {/* Convidar */}
                    <SectionCard
                        title={t('panel.account.invite')}
                        description={t('panel.account.invite_hint')}
                    >
                        <form
                            className="flex flex-wrap items-end gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                invite.post(
                                    route('panel.account.invitations.store'),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => invite.reset(),
                                    },
                                );
                            }}
                            data-invite-form
                        >
                            <div className="grid min-w-0 flex-[2_1_16rem] gap-2">
                                <Label htmlFor="invite-email">
                                    {t('panel.account.invite_email')}
                                </Label>
                                <Input
                                    id="invite-email"
                                    type="email"
                                    value={invite.data.email}
                                    onChange={(event) =>
                                        invite.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={255}
                                    autoComplete="off"
                                    aria-invalid={
                                        invite.errors.email ? true : undefined
                                    }
                                />
                                <InputError message={invite.errors.email} />
                            </div>
                            <div className="grid min-w-0 flex-[1_1_10rem] gap-2">
                                <Label htmlFor="invite-role">
                                    {t('panel.account.role')}
                                </Label>
                                <Select
                                    value={invite.data.role}
                                    onValueChange={(value) =>
                                        invite.setData('role', value)
                                    }
                                >
                                    <SelectTrigger id="invite-role">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roleOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={invite.errors.role} />
                            </div>
                            <Button type="submit" disabled={invite.processing}>
                                {invite.processing ? <Spinner /> : <UserPlus />}
                                {t('panel.account.invite_submit')}
                            </Button>
                        </form>
                    </SectionCard>

                    {/* Convites em aberto */}
                    <section className="space-y-3" data-invitations>
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <h2 className="text-lg font-semibold">
                                {t('panel.account.invitations')}
                            </h2>
                            {invitations.length > 0 && (
                                <ViewToggle
                                    value={invitationsMode}
                                    onChange={setInvitationsMode}
                                />
                            )}
                        </div>
                        {errors.invitation && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    {errors.invitation}
                                </AlertDescription>
                            </Alert>
                        )}
                        {invitations.length === 0 ? (
                            <p
                                className="text-sm text-muted-foreground"
                                data-no-invitations
                            >
                                {t('panel.account.no_invitations')}
                            </p>
                        ) : (
                            <InvitationsList
                                invitations={invitations}
                                mode={invitationsMode}
                                onResend={resend}
                                onRevoke={setRevoking}
                            />
                        )}
                    </section>
                </>
            )}

            {/* Propriedade e saída */}
            {(can.transfer || can.leave || can.delete) && (
                <SectionCard
                    title={t('panel.account.ownership')}
                    description={t('panel.account.ownership_hint')}
                >
                    <div className="space-y-6">
                        {can.transfer && (
                            <div data-transfer>
                                <h3 className="font-medium">
                                    {t('panel.account.transfer')}
                                </h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('panel.account.transfer_hint')}
                                </p>
                                {candidates.length === 0 ? (
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        {t('panel.account.transfer_nobody')}
                                    </p>
                                ) : (
                                    <form
                                        className="mt-3 flex flex-wrap items-end gap-3"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            checkSensitiveAction(
                                                route(
                                                    'panel.account.transfer.code',
                                                ),
                                                { transfer_to: transferTo },
                                                () => setSensitive('transfer'),
                                            );
                                        }}
                                    >
                                        <div className="grid min-w-0 flex-[1_1_16rem] gap-2">
                                            <Label htmlFor="transfer-to">
                                                {t('panel.account.transfer_to')}
                                            </Label>
                                            <Select
                                                value={transferTo}
                                                onValueChange={setTransferTo}
                                            >
                                                <SelectTrigger id="transfer-to">
                                                    <SelectValue
                                                        placeholder={t(
                                                            'panel.account.transfer_choose',
                                                        )}
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {candidates.map(
                                                        (candidate) => (
                                                            <SelectItem
                                                                key={
                                                                    candidate.uuid
                                                                }
                                                                value={
                                                                    candidate.uuid
                                                                }
                                                            >
                                                                {candidate.name}{' '}
                                                                —{' '}
                                                                {
                                                                    candidate.email
                                                                }
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.transfer_to}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            data-transfer-submit
                                        >
                                            <ArrowLeftRight />
                                            {t('panel.account.transfer_submit')}
                                        </Button>
                                    </form>
                                )}
                            </div>
                        )}

                        {can.leave && (
                            <div
                                className="flex flex-wrap items-center justify-between gap-3"
                                data-leave
                            >
                                <div className="min-w-0">
                                    <h3 className="font-medium">
                                        {t('panel.account.leave')}
                                    </h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {t('panel.account.leave_hint')}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setLeaving(true)}
                                >
                                    <LogOut />
                                    {t('panel.account.leave')}
                                </Button>
                            </div>
                        )}

                        {can.delete && (
                            <>
                                <Separator />
                                <div
                                    className="flex flex-wrap items-center justify-between gap-3"
                                    data-delete-account
                                >
                                    <div className="min-w-0">
                                        <h3 className="font-medium text-destructive">
                                            {t('panel.account.delete')}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {t('panel.account.delete_hint')}
                                        </p>
                                        <InputError
                                            message={errors.delete_account}
                                            className="mt-1.5"
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={() =>
                                            checkSensitiveAction(
                                                route(
                                                    'panel.account.delete.code',
                                                ),
                                                {},
                                                () => setSensitive('delete'),
                                            )
                                        }
                                        data-delete-submit
                                    >
                                        <Trash2 />
                                        {t('panel.account.delete')}
                                    </Button>
                                </div>
                            </>
                        )}

                        {!hasTransactionPassword &&
                            (can.transfer || can.delete) && (
                                <Alert data-needs-transaction-password>
                                    <AlertDescription>
                                        <span>
                                            {t(
                                                'panel.account.sensitive_requires_password',
                                            )}{' '}
                                            <Link
                                                href={route(
                                                    'transaction-password.edit',
                                                )}
                                                className="font-medium text-foreground underline"
                                            >
                                                {t(
                                                    'panel.nav.transaction_password',
                                                )}
                                            </Link>
                                        </span>
                                    </AlertDescription>
                                </Alert>
                            )}
                    </div>
                </SectionCard>
            )}

            {renaming && (
                <NameDialog
                    open
                    onClose={() => setRenaming(false)}
                    title={t('panel.account.rename')}
                    label={t('panel.common.name')}
                    initial={account.name}
                    url={route('panel.account.update')}
                />
            )}

            <ConfirmDialog
                open={removing !== null}
                onClose={() => setRemoving(null)}
                title={t('panel.account.remove_title')}
                description={t('panel.account.remove_warning', {
                    name: removing?.name ?? '',
                    account: account.name,
                })}
                confirmLabel={t('panel.account.remove')}
                onConfirm={remove}
                processing={processing}
                confirmTest="remove"
            />

            <ConfirmDialog
                open={revoking !== null}
                onClose={() => setRevoking(null)}
                title={t('panel.account.revoke_title')}
                description={t('panel.account.revoke_warning', {
                    email: revoking?.email ?? '',
                })}
                confirmLabel={t('panel.account.revoke')}
                onConfirm={revoke}
                processing={processing}
                confirmTest="revoke"
            />

            <ConfirmDialog
                open={leaving}
                onClose={() => setLeaving(false)}
                title={t('panel.account.leave_title')}
                description={t('panel.account.leave_warning', {
                    account: account.name,
                })}
                confirmLabel={t('panel.account.leave')}
                onConfirm={leave}
                processing={processing}
                confirmTest="leave"
            />

            <SensitiveActionDialog
                open={sensitive !== null}
                onClose={() => setSensitive(null)}
                description={
                    sensitive === 'transfer'
                        ? t('panel.account.transfer_confirm', {
                              name: transferName,
                          })
                        : t('panel.account.delete_confirm', {
                              account: account.name,
                          })
                }
                codeUrl={
                    sensitive === 'transfer'
                        ? route('panel.account.transfer.code')
                        : route('panel.account.delete.code')
                }
                confirmUrl={
                    sensitive === 'transfer'
                        ? route('panel.account.transfer')
                        : route('panel.account.destroy')
                }
                confirmMethod={sensitive === 'transfer' ? 'post' : 'delete'}
                data={
                    sensitive === 'transfer' ? { transfer_to: transferTo } : {}
                }
                onConfirmed={() => {
                    setSensitive(null);
                    setTransferTo('');
                }}
            />
        </div>
    );
}

AccountShow.layout = {
    breadcrumbs: [{ title: 'panel.nav.account', route: 'panel.account' }],
};
