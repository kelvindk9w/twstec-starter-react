import { usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { CheckboxField, toggleIn } from '@/components/checkbox-field';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import type { ApiKeyFormData, ProjectOption } from '@/types/api-keys';

/**
 * Formulário de CRIAÇÃO de chave (na mesma tela): nome, validade opcional,
 * escopos ("todas as permissões" por padrão, ou a seleção por recurso e
 * ação) e os projetos vinculados (nenhum = a conta toda). As regras são as
 * do servidor (as mesmas da API v1); criar é ação sensível.
 */
export function ApiKeyForm({
    data,
    onChange,
    onSubmit,
    onCancel,
    projects,
    scopesCatalog,
    processing,
}: {
    data: ApiKeyFormData;
    onChange: (data: ApiKeyFormData) => void;
    onSubmit: () => void;
    onCancel: () => void;
    projects: ProjectOption[];
    scopesCatalog: Record<string, string[]>;
    processing: boolean;
}) {
    const { t } = useTrans();
    const { errors } = usePage().props;
    const set = <K extends keyof ApiKeyFormData>(
        key: K,
        value: ApiKeyFormData[K],
    ) => onChange({ ...data, [key]: value });

    return (
        <SectionCard title={t('panel.api_keys.create_heading')}>
            <form
                className="space-y-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit();
                }}
                data-api-key-form
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="key-name">
                            {t('panel.common.name')}
                        </Label>
                        <Input
                            id="key-name"
                            value={data.name}
                            onChange={(event) =>
                                set('name', event.target.value)
                            }
                            maxLength={100}
                            placeholder={t('panel.api_keys.name_placeholder')}
                            autoFocus
                            aria-invalid={errors.name ? true : undefined}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="key-expires">
                            {t('panel.api_keys.expires_at')} (
                            {t('panel.common.optional')})
                        </Label>
                        <Input
                            id="key-expires"
                            type="datetime-local"
                            value={data.expires_at}
                            onChange={(event) =>
                                set('expires_at', event.target.value)
                            }
                            aria-describedby="key-expires-hint"
                            aria-invalid={errors.expires_at ? true : undefined}
                        />
                        <p
                            id="key-expires-hint"
                            className="text-xs text-muted-foreground"
                        >
                            {t('panel.api_keys.expires_hint')}
                        </p>
                        <InputError message={errors.expires_at} />
                    </div>
                </div>

                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        {t('panel.api_keys.scopes_heading')}
                    </legend>
                    <div className="rounded-lg border bg-muted/40 p-4">
                        <CheckboxField
                            id="all-scopes"
                            label={t('panel.api_keys.scopes_all')}
                            checked={data.all_scopes}
                            onChange={(on) => set('all_scopes', on)}
                        />
                        <p className="mt-1 text-xs text-muted-foreground">
                            {t('panel.api_keys.scopes_all_hint')}
                        </p>
                    </div>
                    {!data.all_scopes && (
                        <>
                            <p className="text-xs text-muted-foreground">
                                {t('panel.api_keys.scopes_hint')}
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {Object.entries(scopesCatalog).map(
                                    ([resource, actions]) => (
                                        <div
                                            key={resource}
                                            className="rounded-lg border p-3"
                                        >
                                            <p className="text-sm font-medium">
                                                {t(
                                                    `panel.api_keys.scope_resource_${resource}`,
                                                )}
                                            </p>
                                            <div className="mt-1.5">
                                                {actions.map((action) => {
                                                    const scope = `${resource}:${action}`;

                                                    return (
                                                        <CheckboxField
                                                            key={scope}
                                                            id={`scope-${resource}-${action}`}
                                                            label={t(
                                                                `panel.api_keys.scope_action_${action}`,
                                                            )}
                                                            checked={data.scopes.includes(
                                                                scope,
                                                            )}
                                                            onChange={(on) =>
                                                                set(
                                                                    'scopes',
                                                                    toggleIn(
                                                                        data.scopes,
                                                                        scope,
                                                                        on,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                            <InputError message={errors.scopes} />
                        </>
                    )}
                </fieldset>

                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        {t('panel.api_keys.projects_heading')}
                    </legend>
                    <p className="text-xs text-muted-foreground">
                        {t('panel.api_keys.projects_hint')}
                    </p>
                    {projects.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('panel.api_keys.projects_empty')}
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {projects.map((project) => (
                                <CheckboxField
                                    key={project.uuid}
                                    id={`new-key-project-${project.uuid}`}
                                    label={project.name}
                                    className="rounded-lg border px-3"
                                    checked={data.project_uuids.includes(
                                        project.uuid,
                                    )}
                                    onChange={(on) =>
                                        set(
                                            'project_uuids',
                                            toggleIn(
                                                data.project_uuids,
                                                project.uuid,
                                                on,
                                            ),
                                        )
                                    }
                                />
                            ))}
                        </div>
                    )}
                    <InputError message={errors.project_uuids} />
                </fieldset>

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="create-key"
                    >
                        {t('panel.common.create')}
                    </Button>
                    <Button type="button" variant="ghost" onClick={onCancel}>
                        {t('panel.common.cancel')}
                    </Button>
                </div>
            </form>
        </SectionCard>
    );
}
