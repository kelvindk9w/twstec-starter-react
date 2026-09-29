import { Link, router, usePage } from '@inertiajs/react';
import { Ban, FolderKanban, KeyRound, Plus, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { ApiKeyForm } from '@/components/api-key-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CopyButton } from '@/components/copy-button';
import { EmptyState } from '@/components/empty-state';
import { IconAction } from '@/components/icon-action';
import { KeyProjectsDialog } from '@/components/key-projects-dialog';
import { PageHeader } from '@/components/page-header';
import { ResponsiveList } from '@/components/responsive-list';
import { RotateKeyDialog } from '@/components/rotate-key-dialog';
import { SecretReveal } from '@/components/secret-reveal';
import {
    checkSensitiveAction,
    SensitiveActionDialog,
} from '@/components/sensitive-action-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ViewToggle } from '@/components/view-toggle';
import { useViewMode } from '@/hooks/use-view-mode';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';
import type { FlashData } from '@/types';
import type {
    ApiKeyFormData,
    ApiKeyItem,
    ProjectOption,
} from '@/types/api-keys';

type Props = {
    keys: ApiKeyItem[];
    projects: ProjectOption[];
    scopesCatalog: Record<string, string[]>;
    graceOptions: number[];
    canManageKeys: boolean;
};

type Revealed = NonNullable<FlashData['revealedKey']>;

type Sensitive =
    | { action: 'create' }
    | { action: 'rotate'; key: ApiKeyItem; grace: number };

const emptyForm: ApiKeyFormData = {
    name: '',
    expires_at: '',
    all_scopes: true,
    scopes: [],
    project_uuids: [],
};

/**
 * Chaves de API da conta atual — a mesma tela do starter Livewire: listar,
 * criar (escopos + projetos), a secreta UMA vez, rotacionar com transição,
 * revogar e editar os projetos. Gerir é de owner/admin (member só vê); criar
 * e rotacionar pedem a confirmação de segurança.
 *
 * A secreta chega como dado de UMA resposta do Inertia (`page.flash`, fora do
 * histórico do navegador) e fica só na memória desta tela até "Já guardei".
 */
export default function ApiKeys({
    keys,
    projects,
    scopesCatalog,
    graceOptions,
    canManageKeys,
}: Props) {
    const { t } = useTrans();
    const { route } = useRoute();
    const page = usePage();
    const user = page.props.auth.user;
    const [mode, setMode] = useViewMode('api-keys');

    // A secreta recém-chegada (dado de uma resposta só) fica aqui até "Já guardei".
    const incoming = page.flash.revealedKey ?? null;
    const [revealed, setRevealed] = useState<Revealed | null>(incoming);
    const [lastIncoming, setLastIncoming] = useState<Revealed | null>(incoming);

    if (incoming !== lastIncoming) {
        setLastIncoming(incoming);

        if (incoming) {
            setRevealed(incoming);
        }
    }

    const [creating, setCreating] = useState(false);
    const [form, setForm] = useState<ApiKeyFormData>(emptyForm);
    const [sensitive, setSensitive] = useState<Sensitive | null>(null);
    const [rotating, setRotating] = useState<ApiKeyItem | null>(null);
    const [grace, setGrace] = useState(0);
    const [revoking, setRevoking] = useState<ApiKeyItem | null>(null);
    const [editingProjects, setEditingProjects] = useState<ApiKeyItem | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const dismissSecret = () => {
        setRevealed(null);
        router.flash(() => ({}));
    };

    const requestCreate = () =>
        checkSensitiveAction(route('panel.api-keys.code'), form, () =>
            setSensitive({ action: 'create' }),
        );

    const requestRotate = () => {
        if (!rotating) {
            return;
        }

        const key = rotating;

        checkSensitiveAction(
            route('panel.api-keys.rotate.code', { key: key.uuid }),
            { grace_minutes: grace },
            () => {
                setRotating(null);
                setSensitive({ action: 'rotate', key, grace });
            },
        );
    };

    const revoke = () => {
        if (!revoking) {
            return;
        }

        router.delete(route('panel.api-keys.revoke', { key: revoking.uuid }), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setRevoking(null);
            },
        });
    };

    const projectsLine = (key: ApiKeyItem) =>
        !key.restricted
            ? t('panel.api_keys.whole_account')
            : key.projects.length === 0
              ? t('panel.api_keys.no_projects')
              : key.projects.map((p) => p.name).join(', ');

    const statusBadge = (key: ApiKeyItem) => (
        <Badge
            variant={key.usable ? 'default' : 'secondary'}
            className={
                key.usable
                    ? 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                    : undefined
            }
        >
            {key.statusLabel}
        </Badge>
    );

    const actions = (key: ApiKeyItem) =>
        key.usable && canManageKeys ? (
            <span className="flex items-center justify-end gap-1">
                <IconAction
                    icon={FolderKanban}
                    tone="blue"
                    label={t('panel.api_keys.projects_heading')}
                    onClick={() => setEditingProjects(key)}
                    data-action="projects"
                />
                <IconAction
                    icon={RefreshCw}
                    tone="amber"
                    label={t('panel.api_keys.rotate')}
                    onClick={() => {
                        setGrace(0);
                        setRotating(key);
                    }}
                    data-action="rotate"
                />
                <IconAction
                    icon={Ban}
                    tone="red"
                    label={t('panel.api_keys.revoke')}
                    onClick={() => setRevoking(key)}
                    data-action="revoke"
                />
            </span>
        ) : (
            <span className="block text-right text-xs text-muted-foreground">
                —
            </span>
        );

    const publicKey = (key: ApiKeyItem) => (
        <span className="flex min-w-0 items-center gap-1">
            <code className="truncate font-mono text-xs text-muted-foreground">
                {key.publicKey}
            </code>
            <CopyButton
                value={key.publicKey}
                label={t('panel.api_keys.copy_public')}
                copiedLabel={t('panel.api_keys.copied_public')}
                iconOnly
            />
        </span>
    );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <PageHeader
                    title={t('panel.api_keys.title')}
                    description={t('panel.api_keys.subtitle')}
                />
                {canManageKeys && !creating && (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setCreating(true)}
                        data-test="new-key"
                    >
                        <Plus />
                        {t('panel.api_keys.new')}
                    </Button>
                )}
            </div>

            {canManageKeys && user && !user.hasTransactionPassword && (
                <Alert data-needs-transaction-password>
                    <AlertDescription>
                        <span>
                            {t('panel.api_keys.sensitive_requires_password')}{' '}
                            <Link
                                href={route('transaction-password.edit')}
                                className="font-medium text-foreground underline"
                            >
                                {t('panel.nav.transaction_password')}
                            </Link>
                        </span>
                    </AlertDescription>
                </Alert>
            )}

            {revealed && (
                <SecretReveal
                    publicKey={revealed.publicKey}
                    secret={revealed.secret}
                    onDone={dismissSecret}
                />
            )}

            {creating && (
                <ApiKeyForm
                    data={form}
                    onChange={setForm}
                    onSubmit={requestCreate}
                    onCancel={() => {
                        setCreating(false);
                        setForm(emptyForm);
                    }}
                    projects={projects}
                    scopesCatalog={scopesCatalog}
                    processing={sensitive !== null}
                />
            )}

            {keys.length === 0 ? (
                !creating && (
                    <EmptyState
                        icon={KeyRound}
                        title={t('panel.api_keys.empty_title')}
                        description={t('panel.api_keys.empty')}
                    >
                        {canManageKeys && (
                            <Button
                                type="button"
                                onClick={() => setCreating(true)}
                            >
                                {t('panel.api_keys.new')}
                            </Button>
                        )}
                    </EmptyState>
                )
            ) : (
                <section className="space-y-3" data-api-keys>
                    <div className="flex justify-end">
                        <ViewToggle value={mode} onChange={setMode} />
                    </div>
                    <ResponsiveList
                        mode={mode}
                        table={
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            {t('panel.common.name')}
                                        </TableHead>
                                        <TableHead>
                                            {t('panel.api_keys.public_key')}
                                        </TableHead>
                                        <TableHead>
                                            {t('panel.api_keys.last_used')}
                                        </TableHead>
                                        <TableHead>
                                            {t('panel.api_keys.expires_at')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('panel.common.actions')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {keys.map((key) => (
                                        <TableRow
                                            key={key.uuid}
                                            data-api-key={key.publicKey}
                                        >
                                            <TableCell className="max-w-72">
                                                <span className="flex flex-wrap items-center gap-2">
                                                    <span className="truncate font-medium">
                                                        {key.name}
                                                    </span>
                                                    {statusBadge(key)}
                                                </span>
                                                <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                                                    {t(
                                                        'panel.api_keys.projects_heading',
                                                    )}
                                                    : {projectsLine(key)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="max-w-56">
                                                {publicKey(key)}
                                            </TableCell>
                                            <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                                {key.lastUsedAt ??
                                                    t('panel.common.never')}
                                            </TableCell>
                                            <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                                {key.expiresAt ??
                                                    t(
                                                        'panel.api_keys.no_expiration',
                                                    )}
                                            </TableCell>
                                            <TableCell>
                                                {actions(key)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        }
                        cards={keys.map((key) => (
                            <Card
                                key={key.uuid}
                                className="py-4"
                                data-api-key={key.publicKey}
                            >
                                <CardContent className="space-y-3 px-4">
                                    <div className="flex min-w-0 items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {key.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {projectsLine(key)}
                                            </p>
                                        </div>
                                        {statusBadge(key)}
                                    </div>
                                    {publicKey(key)}
                                    <dl className="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <dt className="text-muted-foreground">
                                                {t('panel.api_keys.last_used')}
                                            </dt>
                                            <dd>
                                                {key.lastUsedAt ??
                                                    t('panel.common.never')}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                {t('panel.api_keys.expires_at')}
                                            </dt>
                                            <dd>
                                                {key.expiresAt ??
                                                    t(
                                                        'panel.api_keys.no_expiration',
                                                    )}
                                            </dd>
                                        </div>
                                    </dl>
                                    {actions(key)}
                                </CardContent>
                            </Card>
                        ))}
                    />
                </section>
            )}

            <RotateKeyDialog
                open={rotating !== null}
                onClose={() => setRotating(null)}
                options={graceOptions}
                value={grace}
                onChange={setGrace}
                onConfirm={requestRotate}
            />

            {editingProjects && (
                <KeyProjectsDialog
                    key={editingProjects.uuid}
                    url={route('panel.api-keys.projects', {
                        key: editingProjects.uuid,
                    })}
                    projects={projects}
                    initial={editingProjects.projects.map((p) => p.uuid)}
                    onClose={() => setEditingProjects(null)}
                />
            )}

            <ConfirmDialog
                open={revoking !== null}
                onClose={() => setRevoking(null)}
                title={t('panel.api_keys.revoke_title')}
                description={t('panel.api_keys.revoke_warning', {
                    name: revoking?.name ?? '',
                })}
                confirmLabel={t('panel.api_keys.revoke')}
                onConfirm={revoke}
                processing={processing}
                confirmTest="revoke-key"
            />

            <SensitiveActionDialog
                open={sensitive !== null}
                onClose={() => setSensitive(null)}
                description={
                    sensitive?.action === 'rotate'
                        ? t('panel.api_keys.rotate_warning')
                        : t('panel.api_keys.create_heading')
                }
                codeUrl={
                    sensitive?.action === 'rotate'
                        ? route('panel.api-keys.rotate.code', {
                              key: sensitive.key.uuid,
                          })
                        : route('panel.api-keys.code')
                }
                confirmUrl={
                    sensitive?.action === 'rotate'
                        ? route('panel.api-keys.rotate', {
                              key: sensitive.key.uuid,
                          })
                        : route('panel.api-keys.store')
                }
                data={
                    sensitive?.action === 'rotate'
                        ? { grace_minutes: sensitive.grace }
                        : form
                }
                onConfirmed={() => {
                    setSensitive(null);
                    setCreating(false);
                    setForm(emptyForm);
                }}
            />
        </div>
    );
}

ApiKeys.layout = {
    breadcrumbs: [{ title: 'panel.nav.api_keys', route: 'panel.api-keys' }],
};
