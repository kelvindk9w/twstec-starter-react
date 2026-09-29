import { Form } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

type Preference = {
    key: string;
    label: string;
    hint: string;
    locked: boolean;
    enabled: boolean;
};

/**
 * Preferências de notificação (catálogo em config/notifications.php). Alerta
 * de segurança é sempre enviado: aparece marcado e travado, e o servidor o
 * grava ligado venha o que vier.
 */
export default function Notifications({
    preferences,
}: {
    preferences: Preference[];
}) {
    const { t } = useTrans();
    const { route } = useRoute();

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={t('panel.notifications.title')}
                description={t('panel.notifications.subtitle')}
            />

            <SectionCard title={t('panel.notifications.emails_heading')}>
                <Form
                    action={route('panel.notifications.update')}
                    method="put"
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing }) => (
                        <>
                            <ul className="divide-y">
                                {preferences.map((preference) => (
                                    <li
                                        key={preference.key}
                                        className="flex items-start gap-3 py-3"
                                    >
                                        <Checkbox
                                            id={`pref-${preference.key}`}
                                            name={`preferences[${preference.key}]`}
                                            value="1"
                                            defaultChecked={
                                                preference.enabled ||
                                                preference.locked
                                            }
                                            disabled={preference.locked}
                                            aria-describedby={`pref-${preference.key}-hint`}
                                        />
                                        <div className="grid gap-1">
                                            <Label
                                                htmlFor={`pref-${preference.key}`}
                                                className="flex items-center gap-2"
                                            >
                                                {preference.label}
                                                {preference.locked && (
                                                    <Badge variant="secondary">
                                                        {t(
                                                            'panel.notifications.locked',
                                                        )}
                                                    </Badge>
                                                )}
                                            </Label>
                                            <p
                                                id={`pref-${preference.key}-hint`}
                                                className="text-sm text-muted-foreground"
                                            >
                                                {preference.hint}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {t('panel.notifications.save')}
                            </Button>
                        </>
                    )}
                </Form>
            </SectionCard>
        </div>
    );
}

Notifications.layout = {
    breadcrumbs: [
        { title: 'panel.nav.notifications', route: 'panel.notifications' },
    ],
};
