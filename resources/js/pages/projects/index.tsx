import { useForm, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Folder,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { IconAction } from '@/components/icon-action';
import InputError from '@/components/input-error';
import { NameDialog } from '@/components/name-dialog';
import { PageHeader } from '@/components/page-header';
import { ResponsiveList } from '@/components/responsive-list';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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

type Project = {
    uuid: string;
    name: string;
    code: string;
    status: 'active' | 'archived';
    statusLabel: string;
    keysCount: number;
};

type Props = {
    projects: Project[];
    can: { create: boolean; update: boolean; delete: boolean };
};

/**
 * Projetos da conta atual — a mesma tela do starter Livewire: criar, renomear,
 * arquivar/reativar e excluir, tudo aqui. O que o papel não permite não
 * aparece (member não exclui) — e o servidor recusa do mesmo jeito.
 */
export default function Projects({ projects, can }: Props) {
    const { t } = useTrans();
    const { route } = useRoute();
    const [mode, setMode] = useViewMode('projects');

    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<Project | null>(null);
    const [deleting, setDeleting] = useState<Project | null>(null);
    const [processing, setProcessing] = useState(false);

    const form = useForm({ name: '' });

    const create = () =>
        form.post(route('panel.projects.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setCreating(false);
            },
        });

    const toggleArchive = (project: Project) =>
        router.patch(
            route('panel.projects.update', { project: project.uuid }),
            { status: project.status === 'active' ? 'archived' : 'active' },
            { preserveScroll: true },
        );

    const destroy = () => {
        if (!deleting) {
            return;
        }

        router.delete(
            route('panel.projects.destroy', { project: deleting.uuid }),
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setDeleting(null);
                },
            },
        );
    };

    const actions = (project: Project) => (
        <span className="flex items-center justify-end gap-1">
            {can.update && (
                <>
                    <IconAction
                        icon={Pencil}
                        tone="blue"
                        label={t('panel.projects.edit')}
                        onClick={() => setEditing(project)}
                        data-action="edit"
                    />
                    {project.status === 'active' ? (
                        <IconAction
                            icon={Archive}
                            tone="amber"
                            label={t('ui.projects.archive')}
                            onClick={() => toggleArchive(project)}
                            data-action="archive"
                        />
                    ) : (
                        <IconAction
                            icon={ArchiveRestore}
                            tone="green"
                            label={t('ui.projects.unarchive')}
                            onClick={() => toggleArchive(project)}
                            data-action="unarchive"
                        />
                    )}
                </>
            )}
            {can.delete && (
                <IconAction
                    icon={Trash2}
                    tone="red"
                    label={t('panel.projects.delete_title')}
                    onClick={() => setDeleting(project)}
                    data-action="delete"
                />
            )}
        </span>
    );

    const status = (project: Project) => (
        <Badge variant={project.status === 'active' ? 'default' : 'secondary'}>
            {project.statusLabel}
        </Badge>
    );

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <PageHeader
                    title={t('panel.projects.title')}
                    description={t('panel.projects.subtitle')}
                />
                {can.create && !creating && (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setCreating(true)}
                        data-test="new-project"
                    >
                        <Plus />
                        {t('panel.projects.new')}
                    </Button>
                )}
            </div>

            {creating && (
                <SectionCard title={t('panel.projects.new')}>
                    <form
                        className="flex flex-wrap items-end gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            create();
                        }}
                    >
                        <div className="grid min-w-0 flex-1 basis-64 gap-2">
                            <Label htmlFor="project-name">
                                {t('panel.common.name')}
                            </Label>
                            <Input
                                id="project-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                maxLength={255}
                                placeholder={t(
                                    'panel.projects.name_placeholder',
                                )}
                                autoFocus
                                aria-invalid={
                                    form.errors.name ? true : undefined
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                {t('panel.common.create')}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    form.reset();
                                    form.clearErrors();
                                    setCreating(false);
                                }}
                            >
                                {t('panel.common.cancel')}
                            </Button>
                        </div>
                    </form>
                </SectionCard>
            )}

            {projects.length === 0 ? (
                !creating && (
                    <EmptyState
                        icon={Folder}
                        title={t('panel.projects.empty_title')}
                        description={t('panel.projects.empty')}
                    >
                        {can.create && (
                            <Button
                                type="button"
                                onClick={() => setCreating(true)}
                            >
                                {t('panel.projects.new')}
                            </Button>
                        )}
                    </EmptyState>
                )
            ) : (
                <section className="space-y-3" data-projects>
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
                                            {t('panel.projects.code')}
                                        </TableHead>
                                        <TableHead>
                                            {t('panel.projects.keys_column')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('panel.common.actions')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {projects.map((project) => (
                                        <TableRow
                                            key={project.uuid}
                                            data-project={project.code}
                                        >
                                            <TableCell>
                                                <span className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium">
                                                        {project.name}
                                                    </span>
                                                    {status(project)}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <code className="font-mono text-xs text-muted-foreground">
                                                    {project.code}
                                                </code>
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {t(
                                                    'panel.projects.linked_keys',
                                                    {
                                                        count: project.keysCount,
                                                    },
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {actions(project)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        }
                        cards={projects.map((project) => (
                            <Card
                                key={project.uuid}
                                className="py-4"
                                data-project={project.code}
                            >
                                <CardContent className="space-y-3 px-4">
                                    <div className="flex min-w-0 items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {project.name}
                                            </p>
                                            <code className="font-mono text-xs text-muted-foreground">
                                                {project.code}
                                            </code>
                                        </div>
                                        {status(project)}
                                    </div>
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-xs text-muted-foreground">
                                            {t('panel.projects.linked_keys', {
                                                count: project.keysCount,
                                            })}
                                        </span>
                                        {actions(project)}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    />
                </section>
            )}

            {editing && (
                <NameDialog
                    key={editing.uuid}
                    open
                    onClose={() => setEditing(null)}
                    title={t('panel.projects.edit')}
                    label={t('panel.common.name')}
                    initial={editing.name}
                    url={route('panel.projects.update', {
                        project: editing.uuid,
                    })}
                />
            )}

            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                title={t('panel.projects.delete_title')}
                description={t('panel.projects.delete_warning', {
                    name: deleting?.name ?? '',
                })}
                confirmLabel={t('panel.common.delete')}
                onConfirm={destroy}
                processing={processing}
                confirmTest="delete-project"
            />
        </div>
    );
}

Projects.layout = {
    breadcrumbs: [{ title: 'panel.nav.projects', route: 'panel.projects' }],
};
