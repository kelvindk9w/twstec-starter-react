import { Link, router, usePage } from '@inertiajs/react';
import {
    Eye,
    Pencil,
    Plus,
    Power,
    RefreshCw,
    Send,
    ShieldAlert,
    Trash2,
    Webhook,
} from 'lucide-react';
import { useState } from 'react';
import { CheckboxField, toggleIn } from '@/components/checkbox-field';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CopyButton } from '@/components/copy-button';
import { EmptyState } from '@/components/empty-state';
import { IconAction } from '@/components/icon-action';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import {
    checkSensitiveAction,
    SensitiveActionDialog,
} from '@/components/sensitive-action-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';
import type { FlashData } from '@/types';
import type {
    WebhookDeliveryItem,
    WebhookEndpointItem,
    WebhookFormData,
    WebhookOption,
} from '@/types/webhooks';

type Props = {
    endpoints: WebhookEndpointItem[];
    deliveries: WebhookDeliveryItem[];
    deliveriesFor: string | null;
    eventOptions: WebhookOption[];
    projectOptions: { uuid: string; name: string }[];
    canManage: boolean;
    defaultOverlap: number;
    maxOverlap: number;
};

type Revealed = NonNullable<FlashData['revealedWebhookSecret']>;

type Sensitive =
    | { action: 'create' }
    | { action: 'update'; endpoint: WebhookEndpointItem }
    | { action: 'reveal'; endpoint: WebhookEndpointItem }
    | { action: 'rotate'; endpoint: WebhookEndpointItem; overlap: number };

const emptyForm: WebhookFormData = {
    name: '',
    url: '',
    events: [],
    project: '',
};

const statusTone: Record<string, string> = {
    succeeded:
        'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    failed: 'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
    retrying:
        'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
};

const when = (iso: string | null): string =>
    iso ? new Date(iso).toLocaleString() : '—';

/**
 * Webhooks da conta atual — a mesma tela do starter Livewire: endpoints
 * (criar, editar, ativar/desativar, excluir, enviar teste, revelar e
 * rotacionar o segredo), o log de entregas com as tentativas e o reenvio.
 * Gerir é de dono/admin (membro só vê); criar, editar, revelar e rotacionar
 * pedem a confirmação de segurança.
 *
 * O segredo chega como dado de UMA resposta do Inertia (`page.flash`, fora do
 * histórico do navegador) e fica só na memória desta tela até "Já guardei".
 */
export default function Webhooks({
    endpoints,
    deliveries,
    deliveriesFor,
    eventOptions,
    projectOptions,
    canManage,
    defaultOverlap,
    maxOverlap,
}: Props) {
    const { t } = useTrans();
    const { route } = useRoute();
    const page = usePage();
    const { errors } = page.props;
    const user = page.props.auth.user;

    const incoming = page.flash.revealedWebhookSecret ?? null;
    const [revealed, setRevealed] = useState<Revealed | null>(incoming);
    const [lastIncoming, setLastIncoming] = useState<Revealed | null>(incoming);

    if (incoming !== lastIncoming) {
        setLastIncoming(incoming);

        if (incoming) {
            setRevealed(incoming);
        }
    }

    const [editing, setEditing] = useState<WebhookEndpointItem | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState<WebhookFormData>(emptyForm);
    const [sensitive, setSensitive] = useState<Sensitive | null>(null);
    const [rotating, setRotating] = useState<WebhookEndpointItem | null>(null);
    const [overlap, setOverlap] = useState(defaultOverlap);
    const [deleting, setDeleting] = useState<WebhookEndpointItem | null>(null);
    const [processing, setProcessing] = useState(false);

    const set = <K extends keyof WebhookFormData>(
        key: K,
        value: WebhookFormData[K],
    ) => setForm({ ...form, [key]: value });

    const dismissSecret = () => {
        setRevealed(null);
        router.flash(() => ({}));
    };

    const startCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setShowForm(true);
    };

    const startEdit = (endpoint: WebhookEndpointItem) => {
        setEditing(endpoint);
        setForm({
            name: endpoint.name,
            url: endpoint.url,
            events: endpoint.events,
            project: endpoint.project?.uuid ?? '',
        });
        setShowForm(true);
    };

    const requestSave = () =>
        editing
            ? checkSensitiveAction(
                  route('panel.webhooks.update.code', {
                      endpoint: editing.uuid,
                  }),
                  form,
                  () => setSensitive({ action: 'update', endpoint: editing }),
              )
            : checkSensitiveAction(route('panel.webhooks.code'), form, () =>
                  setSensitive({ action: 'create' }),
              );

    const requestReveal = (endpoint: WebhookEndpointItem) =>
        checkSensitiveAction(
            route('panel.webhooks.secret.code', { endpoint: endpoint.uuid }),
            { action: 'reveal' },
            () => setSensitive({ action: 'reveal', endpoint }),
        );

    const requestRotate = () => {
        if (!rotating) {
            return;
        }

        const endpoint = rotating;

        checkSensitiveAction(
            route('panel.webhooks.secret.code', { endpoint: endpoint.uuid }),
            { action: 'rotate', overlap_minutes: overlap },
            () => {
                setRotating(null);
                setSensitive({ action: 'rotate', endpoint, overlap });
            },
        );
    };

    const post = (url: string, data: Record<string, string | boolean> = {}) =>
        router.post(url, data, { preserveScroll: true });

    const remove = () => {
        if (!deleting) {
            return;
        }

        router.delete(
            route('panel.webhooks.destroy', { endpoint: deleting.uuid }),
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

    const sensitiveUrls = (s: Sensitive): [string, string, string] => {
        switch (s.action) {
            case 'create':
                return [
                    route('panel.webhooks.code'),
                    route('panel.webhooks.store'),
                    'post',
                ];
            case 'update':
                return [
                    route('panel.webhooks.update.code', {
                        endpoint: s.endpoint.uuid,
                    }),
                    route('panel.webhooks.update', {
                        endpoint: s.endpoint.uuid,
                    }),
                    'put',
                ];
            case 'reveal':
                return [
                    route('panel.webhooks.secret.code', {
                        endpoint: s.endpoint.uuid,
                    }),
                    route('panel.webhooks.reveal', {
                        endpoint: s.endpoint.uuid,
                    }),
                    'post',
                ];
            case 'rotate':
                return [
                    route('panel.webhooks.secret.code', {
                        endpoint: s.endpoint.uuid,
                    }),
                    route('panel.webhooks.rotate', {
                        endpoint: s.endpoint.uuid,
                    }),
                    'post',
                ];
        }
    };

    const sensitiveData = (s: Sensitive): Record<string, unknown> => {
        switch (s.action) {
            case 'create':
            case 'update':
                return form;
            case 'reveal':
                return { action: 'reveal' };
            case 'rotate':
                return { action: 'rotate', overlap_minutes: s.overlap };
        }
    };

    const [codeUrl, confirmUrl, confirmMethod] = sensitive
        ? sensitiveUrls(sensitive)
        : ['', '', 'post'];

    const filtered = endpoints.find((e) => e.uuid === deliveriesFor) ?? null;
    const pageError =
        (errors as Record<string, string | undefined>).endpoint ??
        (errors as Record<string, string | undefined>).delivery ??
        (!showForm
            ? (errors as Record<string, string | undefined>).url
            : undefined);

    return (
        <div
            className="flex flex-1 flex-col gap-6 p-4 md:p-6"
            data-webhooks-page
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <PageHeader
                    title={t('webhooks.ui.title')}
                    description={t('webhooks.ui.subtitle')}
                />
                {canManage && !showForm && (
                    <Button
                        type="button"
                        size="sm"
                        onClick={startCreate}
                        data-test="new-webhook"
                    >
                        <Plus />
                        {t('webhooks.ui.new')}
                    </Button>
                )}
            </div>

            {pageError && (
                <Alert variant="destructive" data-webhooks-error>
                    <AlertDescription>{pageError}</AlertDescription>
                </Alert>
            )}

            {!canManage && (
                <Alert>
                    <AlertDescription>
                        {t('webhooks.ui.read_only')}
                    </AlertDescription>
                </Alert>
            )}

            {canManage && user && !user.hasTransactionPassword && (
                <Alert data-needs-transaction-password>
                    <AlertDescription>
                        <span>
                            {t('webhooks.ui.sensitive_requires_password')}{' '}
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
                <section
                    className="rounded-xl border-2 border-amber-400 bg-amber-50 p-5 dark:border-amber-500 dark:bg-amber-950/40"
                    data-webhook-secret-panel
                >
                    <div className="flex items-start gap-3">
                        <ShieldAlert className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                        <div className="min-w-0">
                            <h2 className="text-lg font-semibold text-amber-900 dark:text-amber-100">
                                {t('webhooks.ui.secret_title')} —{' '}
                                {revealed.name}
                            </h2>
                            <p className="mt-1 text-sm font-medium text-amber-800 dark:text-amber-200">
                                {t('webhooks.ui.secret_once')}
                            </p>
                        </div>
                    </div>
                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        <code
                            className="rounded bg-background px-2 py-1 text-sm font-semibold break-all"
                            data-webhook-secret
                        >
                            {revealed.secret}
                        </code>
                        <CopyButton
                            value={revealed.secret}
                            label={t('webhooks.ui.copy')}
                            copiedLabel={t('webhooks.ui.copied')}
                            variant="default"
                        />
                    </div>
                    <p className="mt-4 text-xs text-amber-800 dark:text-amber-200">
                        {t('webhooks.ui.signature_help')}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        className="mt-5"
                        onClick={dismissSecret}
                        data-webhook-secret-done
                    >
                        {t('webhooks.ui.secret_saved')}
                    </Button>
                </section>
            )}

            {showForm && (
                <SectionCard
                    title={
                        editing ? t('webhooks.ui.edit') : t('webhooks.ui.new')
                    }
                >
                    <form
                        className="space-y-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            requestSave();
                        }}
                        data-webhook-form
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="webhook-name">
                                    {t('webhooks.ui.name')}
                                </Label>
                                <Input
                                    id="webhook-name"
                                    value={form.name}
                                    onChange={(event) =>
                                        set('name', event.target.value)
                                    }
                                    maxLength={100}
                                    autoFocus
                                    aria-invalid={
                                        errors.name ? true : undefined
                                    }
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="webhook-url">
                                    {t('webhooks.ui.url')}
                                </Label>
                                <Input
                                    id="webhook-url"
                                    type="url"
                                    value={form.url}
                                    onChange={(event) =>
                                        set('url', event.target.value)
                                    }
                                    placeholder="https://"
                                    aria-invalid={errors.url ? true : undefined}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('webhooks.ui.url_hint')}
                                </p>
                                <InputError message={errors.url} />
                            </div>
                        </div>

                        <fieldset>
                            <legend className="text-sm font-medium">
                                {t('webhooks.ui.events')}
                            </legend>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {t('webhooks.ui.events_hint')}
                            </p>
                            <div className="mt-2 flex flex-wrap gap-x-4">
                                {eventOptions.map((option) => (
                                    <CheckboxField
                                        key={option.value}
                                        id={`webhook-event-${option.value}`}
                                        label={option.label}
                                        checked={form.events.includes(
                                            option.value,
                                        )}
                                        onChange={(on) =>
                                            set(
                                                'events',
                                                toggleIn(
                                                    form.events,
                                                    option.value,
                                                    on,
                                                ),
                                            )
                                        }
                                    />
                                ))}
                            </div>
                            <InputError
                                message={
                                    errors.events ??
                                    (errors as Record<string, string>)[
                                        'events.0'
                                    ]
                                }
                            />
                        </fieldset>

                        <div className="grid gap-2">
                            <Label htmlFor="webhook-project">
                                {t('webhooks.ui.project')}
                            </Label>
                            <select
                                id="webhook-project"
                                value={form.project}
                                onChange={(event) =>
                                    set('project', event.target.value)
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                            >
                                <option value="">
                                    {t('webhooks.ui.project_all')}
                                </option>
                                {projectOptions.map((project) => (
                                    <option
                                        key={project.uuid}
                                        value={project.uuid}
                                    >
                                        {project.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.project} />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                disabled={sensitive !== null}
                                data-test="save-webhook"
                            >
                                {t('webhooks.ui.save')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setShowForm(false);
                                    setEditing(null);
                                    setForm(emptyForm);
                                }}
                            >
                                {t('webhooks.ui.cancel')}
                            </Button>
                        </div>
                    </form>
                </SectionCard>
            )}

            {endpoints.length === 0 ? (
                !showForm && (
                    <EmptyState
                        icon={Webhook}
                        title={t('webhooks.ui.empty')}
                        description={t('webhooks.ui.empty_hint')}
                    >
                        {canManage && (
                            <Button type="button" onClick={startCreate}>
                                {t('webhooks.ui.new')}
                            </Button>
                        )}
                    </EmptyState>
                )
            ) : (
                <section className="space-y-3" data-webhook-endpoints>
                    {endpoints.map((endpoint) => (
                        <div
                            key={endpoint.uuid}
                            className="rounded-lg border p-4"
                            data-webhook-endpoint={endpoint.uuid}
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="flex flex-wrap items-center gap-2 font-medium">
                                        {endpoint.name}
                                        <Badge
                                            variant={
                                                endpoint.active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                            className={
                                                endpoint.active
                                                    ? statusTone.succeeded
                                                    : undefined
                                            }
                                            data-webhook-status
                                        >
                                            {endpoint.status_label}
                                        </Badge>
                                    </p>
                                    <code className="mt-1 block truncate font-mono text-xs text-muted-foreground">
                                        {endpoint.url}
                                    </code>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {t('webhooks.ui.events')}:{' '}
                                        {endpoint.event_labels.join(', ')}
                                        {endpoint.project &&
                                            ` · ${t('webhooks.ui.project')}: ${endpoint.project.name}`}
                                    </p>
                                    {endpoint.disabled_reason === 'failures' ? (
                                        <p className="mt-1 text-xs text-red-700 dark:text-red-300">
                                            {t(
                                                'webhooks.ui.disabled_by_failures',
                                                {
                                                    count: endpoint.consecutive_failures,
                                                },
                                            )}
                                        </p>
                                    ) : (
                                        endpoint.consecutive_failures > 0 && (
                                            <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                                {t('webhooks.ui.failures', {
                                                    count: endpoint.consecutive_failures,
                                                })}
                                            </p>
                                        )
                                    )}
                                    {endpoint.previous_secret_expires_at && (
                                        <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                            {t(
                                                'webhooks.ui.previous_secret_until',
                                                {
                                                    date: when(
                                                        endpoint.previous_secret_expires_at,
                                                    ),
                                                },
                                            )}
                                        </p>
                                    )}
                                </div>
                                <span className="flex flex-wrap items-center justify-end gap-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.get(
                                                route('panel.webhooks'),
                                                { endpoint: endpoint.uuid },
                                                {
                                                    preserveScroll: true,
                                                    preserveState: true,
                                                },
                                            )
                                        }
                                        data-test="show-deliveries"
                                    >
                                        {t('webhooks.ui.show_deliveries')}
                                    </Button>
                                    {canManage && (
                                        <>
                                            {endpoint.active && (
                                                <IconAction
                                                    icon={Send}
                                                    tone="blue"
                                                    label={t(
                                                        'webhooks.ui.send_test',
                                                    )}
                                                    onClick={() =>
                                                        post(
                                                            route(
                                                                'panel.webhooks.test',
                                                                {
                                                                    endpoint:
                                                                        endpoint.uuid,
                                                                },
                                                            ),
                                                        )
                                                    }
                                                    data-action="test"
                                                />
                                            )}
                                            <IconAction
                                                icon={Pencil}
                                                tone="blue"
                                                label={t('webhooks.ui.edit')}
                                                onClick={() =>
                                                    startEdit(endpoint)
                                                }
                                                data-action="edit"
                                            />
                                            <IconAction
                                                icon={Eye}
                                                tone="amber"
                                                label={t('webhooks.ui.reveal')}
                                                onClick={() =>
                                                    requestReveal(endpoint)
                                                }
                                                data-action="reveal"
                                            />
                                            <IconAction
                                                icon={RefreshCw}
                                                tone="amber"
                                                label={t('webhooks.ui.rotate')}
                                                onClick={() => {
                                                    setOverlap(defaultOverlap);
                                                    setRotating(endpoint);
                                                }}
                                                data-action="rotate"
                                            />
                                            <IconAction
                                                icon={Power}
                                                tone="amber"
                                                label={
                                                    endpoint.active
                                                        ? t(
                                                              'webhooks.ui.disable',
                                                          )
                                                        : t(
                                                              'webhooks.ui.enable',
                                                          )
                                                }
                                                onClick={() =>
                                                    post(
                                                        route(
                                                            'panel.webhooks.status',
                                                            {
                                                                endpoint:
                                                                    endpoint.uuid,
                                                            },
                                                        ),
                                                        {
                                                            active: !endpoint.active,
                                                        },
                                                    )
                                                }
                                                data-action="toggle"
                                            />
                                            <IconAction
                                                icon={Trash2}
                                                tone="red"
                                                label={t('webhooks.ui.delete')}
                                                onClick={() =>
                                                    setDeleting(endpoint)
                                                }
                                                data-action="delete"
                                            />
                                        </>
                                    )}
                                </span>
                            </div>
                        </div>
                    ))}
                </section>
            )}

            {endpoints.length > 0 && (
                <section className="space-y-3" data-webhook-deliveries>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold">
                            {t('webhooks.ui.deliveries')}{' '}
                            <span className="text-sm font-normal text-muted-foreground">
                                —{' '}
                                {filtered?.name ??
                                    t('webhooks.ui.all_endpoints')}
                            </span>
                        </h2>
                        {deliveriesFor && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.get(
                                        route('panel.webhooks'),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('webhooks.ui.all_endpoints')}
                            </Button>
                        )}
                    </div>

                    {deliveries.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('webhooks.ui.deliveries_empty')}
                        </p>
                    ) : (
                        deliveries.map((delivery) => (
                            <details
                                key={delivery.uuid}
                                className="rounded-lg border p-3"
                                data-webhook-delivery={delivery.status}
                            >
                                <summary className="flex cursor-pointer flex-wrap items-center gap-2 text-sm">
                                    <Badge
                                        variant="secondary"
                                        className={statusTone[delivery.status]}
                                    >
                                        {delivery.status_label}
                                    </Badge>
                                    <code className="font-mono text-xs">
                                        {delivery.event.type}
                                    </code>
                                    <span className="text-xs text-muted-foreground">
                                        {delivery.endpoint.name} ·{' '}
                                        {t('webhooks.ui.attempts')}:{' '}
                                        {delivery.attempts}
                                        {delivery.response_status !== null &&
                                            ` · HTTP ${delivery.response_status}`}
                                        {delivery.duration_ms !== null &&
                                            ` · ${t('webhooks.ui.duration', { ms: delivery.duration_ms })}`}{' '}
                                        · {when(delivery.created_at)}
                                    </span>
                                    {canManage && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="ml-auto"
                                            onClick={() =>
                                                post(
                                                    route(
                                                        'panel.webhooks.resend',
                                                        {
                                                            delivery:
                                                                delivery.uuid,
                                                        },
                                                    ),
                                                )
                                            }
                                            data-test="resend"
                                        >
                                            <RefreshCw />
                                            {t('webhooks.ui.resend')}
                                        </Button>
                                    )}
                                </summary>
                                <div className="mt-3 space-y-2 text-xs">
                                    <p className="text-muted-foreground">
                                        {t('webhooks.ui.event')}:{' '}
                                        <code>{delivery.event.uuid}</code>
                                    </p>
                                    {delivery.next_attempt_at && (
                                        <p className="text-muted-foreground">
                                            {t('webhooks.ui.next_attempt', {
                                                date: when(
                                                    delivery.next_attempt_at,
                                                ),
                                            })}
                                        </p>
                                    )}
                                    {delivery.attempt_log.map((attempt) => (
                                        <div
                                            key={attempt.attempt}
                                            className="rounded border p-2"
                                            data-webhook-attempt
                                        >
                                            <p className="font-medium">
                                                {t('webhooks.ui.attempt', {
                                                    number: attempt.attempt,
                                                })}
                                                {attempt.manual &&
                                                    ` (${t('webhooks.ui.manual')})`}{' '}
                                                — {attempt.outcome_label}
                                                {attempt.response_status !==
                                                    null &&
                                                    ` · HTTP ${attempt.response_status}`}
                                                {attempt.duration_ms !== null &&
                                                    ` · ${t('webhooks.ui.duration', { ms: attempt.duration_ms })}`}{' '}
                                                · {when(attempt.created_at)}
                                            </p>
                                            {attempt.error && (
                                                <p className="mt-1 text-red-700 dark:text-red-300">
                                                    {attempt.error}
                                                </p>
                                            )}
                                            {attempt.response_excerpt && (
                                                <pre className="mt-1 max-h-40 overflow-auto rounded bg-muted p-2 font-mono break-all whitespace-pre-wrap">
                                                    {attempt.response_excerpt}
                                                </pre>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </details>
                        ))
                    )}
                </section>
            )}

            <Dialog
                open={rotating !== null}
                onOpenChange={(open) => !open && setRotating(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('webhooks.ui.rotate')}</DialogTitle>
                        <DialogDescription>
                            {t('webhooks.ui.rotate_hint')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="webhook-overlap">
                            {t('webhooks.ui.rotate_overlap')}
                        </Label>
                        <Input
                            id="webhook-overlap"
                            type="number"
                            min={0}
                            max={maxOverlap}
                            value={overlap}
                            onChange={(event) =>
                                setOverlap(Number(event.target.value))
                            }
                        />
                        <InputError message={errors.overlap_minutes} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRotating(null)}
                        >
                            {t('webhooks.ui.cancel')}
                        </Button>
                        <Button
                            type="button"
                            onClick={requestRotate}
                            data-test="confirm-rotate"
                        >
                            {t('panel.common.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                title={t('webhooks.ui.delete')}
                description={t('webhooks.ui.delete_confirm')}
                confirmLabel={t('webhooks.ui.delete')}
                onConfirm={remove}
                processing={processing}
                confirmTest="delete-webhook"
            />

            <SensitiveActionDialog
                open={sensitive !== null}
                onClose={() => setSensitive(null)}
                description={
                    sensitive?.action === 'reveal'
                        ? t('webhooks.ui.reveal')
                        : sensitive?.action === 'rotate'
                          ? t('webhooks.ui.rotate')
                          : sensitive?.action === 'update'
                            ? t('webhooks.ui.edit')
                            : t('webhooks.ui.new')
                }
                codeUrl={codeUrl}
                confirmUrl={confirmUrl}
                confirmMethod={confirmMethod as 'post' | 'put'}
                data={sensitive ? sensitiveData(sensitive) : {}}
                onConfirmed={() => {
                    setSensitive(null);
                    setShowForm(false);
                    setEditing(null);
                    setForm(emptyForm);
                }}
            />
        </div>
    );
}

Webhooks.layout = {
    breadcrumbs: [{ title: 'panel.nav.webhooks', route: 'panel.webhooks' }],
};
