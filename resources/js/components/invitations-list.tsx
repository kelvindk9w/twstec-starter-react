import { Send, X } from 'lucide-react';
import { IconAction } from '@/components/icon-action';
import { ResponsiveList } from '@/components/responsive-list';
import { RoleBadge } from '@/components/role-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ViewMode } from '@/hooks/use-view-mode';
import { useTrans } from '@/lib/i18n';
import type { Invitation } from '@/types/account';

/**
 * Os convites em aberto (pendentes e expirados) — reenviar troca o link e
 * renova a validade; revogar mata o link na hora.
 */
export function InvitationsList({
    invitations,
    mode,
    onResend,
    onRevoke,
}: {
    invitations: Invitation[];
    mode: ViewMode;
    onResend: (invitation: Invitation) => void;
    onRevoke: (invitation: Invitation) => void;
}) {
    const { t } = useTrans();

    const status = (invitation: Invitation) => (
        <Badge
            className={
                invitation.pending
                    ? 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'
                    : 'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
            }
        >
            {invitation.statusLabel}
        </Badge>
    );

    const actions = (invitation: Invitation) => (
        <span className="flex items-center justify-end gap-1">
            <IconAction
                icon={Send}
                tone="blue"
                label={t('panel.account.resend')}
                onClick={() => onResend(invitation)}
                data-action="resend"
            />
            <IconAction
                icon={X}
                tone="red"
                label={t('panel.account.revoke')}
                onClick={() => onRevoke(invitation)}
                data-action="revoke"
            />
        </span>
    );

    const who = (invitation: Invitation) => (
        <span className="min-w-0">
            <span className="block truncate font-medium">
                {invitation.email}
            </span>
            {invitation.invitedBy && (
                <span className="block truncate text-xs text-muted-foreground">
                    {t('panel.account.invited_by', {
                        name: invitation.invitedBy,
                    })}
                </span>
            )}
        </span>
    );

    return (
        <ResponsiveList
            mode={mode}
            table={
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>
                                {t('panel.account.invite_email')}
                            </TableHead>
                            <TableHead>{t('panel.account.role')}</TableHead>
                            <TableHead>{t('panel.common.status')}</TableHead>
                            <TableHead>{t('panel.account.expires')}</TableHead>
                            <TableHead className="text-right">
                                {t('panel.common.actions')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {invitations.map((invitation) => (
                            <TableRow
                                key={invitation.uuid}
                                data-invitation={invitation.email}
                            >
                                <TableCell className="max-w-72">
                                    {who(invitation)}
                                </TableCell>
                                <TableCell>
                                    <RoleBadge
                                        role={invitation.role}
                                        label={invitation.roleLabel}
                                    />
                                </TableCell>
                                <TableCell>{status(invitation)}</TableCell>
                                <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                    {invitation.expiresAt}
                                </TableCell>
                                <TableCell>{actions(invitation)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            }
            cards={invitations.map((invitation) => (
                <Card
                    key={invitation.uuid}
                    className="py-4"
                    data-invitation={invitation.email}
                >
                    <CardContent className="space-y-3 px-4">
                        <div className="flex min-w-0 items-start justify-between gap-2">
                            {who(invitation)}
                            {status(invitation)}
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                <RoleBadge
                                    role={invitation.role}
                                    label={invitation.roleLabel}
                                />
                                {invitation.expiresAt}
                            </span>
                            {actions(invitation)}
                        </div>
                    </CardContent>
                </Card>
            ))}
        />
    );
}
