import { MemberActions } from '@/components/member-actions';
import { ResponsiveList } from '@/components/responsive-list';
import { RoleBadge } from '@/components/role-badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { UserAvatar } from '@/components/user-info';
import type { ViewMode } from '@/hooks/use-view-mode';
import { useTrans } from '@/lib/i18n';
import type { Member } from '@/types/account';

/**
 * Os membros da conta — em tabela ou cartões — com avatar, papel, desde
 * quando e as ações (só ícone, com tooltip).
 */
export function MembersList({
    members,
    mode,
    onChangeRole,
    onRemove,
}: {
    members: Member[];
    mode: ViewMode;
    onChangeRole: (member: Member, role: 'admin' | 'member') => void;
    onRemove: (member: Member) => void;
}) {
    const { t } = useTrans();

    const person = (member: Member, size: string) => (
        <span className="flex min-w-0 items-center gap-3">
            <UserAvatar user={member} className={size} />
            <span className="min-w-0">
                <span className="block truncate font-medium">
                    {member.name}
                    {member.self && (
                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                            ({t('panel.account.you')})
                        </span>
                    )}
                </span>
                <span className="block truncate text-xs text-muted-foreground">
                    {member.email}
                </span>
            </span>
        </span>
    );

    const actions = (member: Member) => (
        <MemberActions
            member={member}
            onChangeRole={onChangeRole}
            onRemove={onRemove}
        />
    );

    return (
        <ResponsiveList
            mode={mode}
            table={
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('panel.account.person')}</TableHead>
                            <TableHead>{t('panel.account.role')}</TableHead>
                            <TableHead>{t('panel.account.since')}</TableHead>
                            <TableHead className="text-right">
                                {t('panel.common.actions')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {members.map((member) => (
                            <TableRow
                                key={member.uuid}
                                data-member={member.email}
                            >
                                <TableCell className="max-w-80">
                                    {person(member, 'size-8')}
                                </TableCell>
                                <TableCell>
                                    <RoleBadge
                                        role={member.role}
                                        label={member.roleLabel}
                                        data-member-role
                                    />
                                </TableCell>
                                <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                    {member.joined}
                                </TableCell>
                                <TableCell>{actions(member)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            }
            cards={members.map((member) => (
                <Card
                    key={member.uuid}
                    className="py-4"
                    data-member={member.email}
                >
                    <CardContent className="space-y-3 px-4">
                        {person(member, 'size-10')}
                        <div className="flex items-center justify-between gap-2">
                            <RoleBadge
                                role={member.role}
                                label={member.roleLabel}
                                data-member-role
                            />
                            {actions(member)}
                        </div>
                    </CardContent>
                </Card>
            ))}
        />
    );
}
