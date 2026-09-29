import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CheckboxField, toggleIn } from '@/components/checkbox-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import type { ProjectOption } from '@/types/api-keys';

/**
 * Os PROJETOS de uma chave (vínculo N:N): nenhum marcado = a chave enxerga a
 * conta toda; marcados = restrita a eles. O servidor recusa projeto de outra
 * conta.
 */
export function KeyProjectsDialog({
    url,
    projects,
    initial,
    onClose,
}: {
    url: string;
    projects: ProjectOption[];
    initial: string[];
    onClose: () => void;
}) {
    const { t } = useTrans();
    const { errors } = usePage().props;
    const [selected, setSelected] = useState<string[]>(initial);
    const [processing, setProcessing] = useState(false);

    const save = () =>
        router.put(
            url,
            { project_uuids: selected },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: (page) => {
                    if (Object.keys(page.props.errors ?? {}).length === 0) {
                        onClose();
                    }
                },
            },
        );

    return (
        <Dialog open onOpenChange={(next) => !next && onClose()}>
            <DialogContent data-key-projects-dialog>
                <DialogHeader>
                    <DialogTitle>
                        {t('panel.api_keys.projects_heading')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('panel.api_keys.projects_hint')}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    {projects.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('panel.api_keys.projects_empty')}
                        </p>
                    ) : (
                        projects.map((project) => (
                            <CheckboxField
                                key={project.uuid}
                                id={`key-project-${project.uuid}`}
                                label={project.name}
                                className="rounded-lg border px-3"
                                checked={selected.includes(project.uuid)}
                                onChange={(on) =>
                                    setSelected(
                                        toggleIn(selected, project.uuid, on),
                                    )
                                }
                            />
                        ))
                    )}
                </div>
                <InputError message={errors.project_uuids} />
                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={onClose}>
                        {t('panel.common.cancel')}
                    </Button>
                    <Button type="button" disabled={processing} onClick={save}>
                        {processing && <Spinner />}
                        {t('panel.common.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
