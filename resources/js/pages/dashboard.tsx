import { Link, usePage } from '@inertiajs/react';
import { Bell, CircleUser, Lock } from 'lucide-react';
import { BarChart } from '@/components/bar-chart';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

type Overview = {
    activeKeysCount: number;
    projectsCount: number;
    recentRequestsCount: number;
    recentRequestsDays: number;
    lastKeyUsedAt: { at: string; ago: string } | null;
    chartDays: number;
    chart: { labels: string[]; values: number[] };
    recentCalls: {
        uuid: string;
        method: string;
        endpoint: string;
        status: number | string;
        tone: 'success' | 'warning' | 'error';
        when: string | null;
    }[];
};

/**
 * Painel inicial. Com o pacote de contas: os números da conta atual
 * (AccountOverviewQuery) — chaves, projetos e o tráfego real da API. Sem
 * ele: os atalhos da conta da pessoa.
 */
export default function Dashboard({ overview }: { overview: Overview | null }) {
    const { t } = useTrans();
    const { auth } = usePage().props;

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={t('panel.dashboard.greeting', {
                    name: auth.user?.name ?? '',
                })}
                description={`${t('panel.dashboard.user_code')}: ${auth.user?.code ?? ''}`}
            />
            {overview ? (
                <AccountOverview overview={overview} />
            ) : (
                <Essentials />
            )}
        </div>
    );
}

function AccountOverview({ overview }: { overview: Overview }) {
    const { t } = useTrans();

    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label={t('panel.dashboard.summary_keys')}
                    value={overview.activeKeysCount}
                />
                <StatCard
                    label={t('panel.dashboard.summary_projects')}
                    value={overview.projectsCount}
                />
                <StatCard
                    label={t('panel.dashboard.summary_requests', {
                        days: overview.recentRequestsDays,
                    })}
                    value={overview.recentRequestsCount}
                />
                <StatCard
                    label={t('panel.dashboard.summary_last_key_use')}
                    value={
                        overview.lastKeyUsedAt?.at ?? t('panel.common.never')
                    }
                    hint={
                        overview.lastKeyUsedAt?.ago ??
                        t('panel.dashboard.last_key_use_empty')
                    }
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        {t('panel.dashboard.chart_title', {
                            days: overview.chartDays,
                        })}
                    </CardTitle>
                    <CardDescription>
                        {t('panel.dashboard.chart_hint')}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {overview.chart.values.some((value) => value > 0) ? (
                        <BarChart
                            labels={overview.chart.labels}
                            values={overview.chart.values}
                            seriesLabel={t('panel.dashboard.chart_series')}
                        />
                    ) : (
                        <EmptyState
                            title={t('panel.dashboard.chart_empty_title')}
                            description={t(
                                'panel.dashboard.chart_empty_description',
                            )}
                        />
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        {t('panel.dashboard.recent_calls_title')}
                    </CardTitle>
                    <CardDescription>
                        {t('panel.dashboard.recent_calls_hint')}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {overview.recentCalls.length === 0 ? (
                        <EmptyState
                            title={t(
                                'panel.dashboard.recent_calls_empty_title',
                            )}
                            description={t(
                                'panel.dashboard.recent_calls_empty_description',
                            )}
                        />
                    ) : (
                        <ul className="divide-y">
                            {overview.recentCalls.map((call) => (
                                <li
                                    key={call.uuid}
                                    className="flex flex-wrap items-center gap-2 py-2.5"
                                >
                                    <Badge
                                        variant="outline"
                                        className="font-mono"
                                    >
                                        {call.method}
                                    </Badge>
                                    <code className="min-w-0 flex-1 truncate font-mono text-xs">
                                        {call.endpoint}
                                    </code>
                                    <Badge
                                        variant={
                                            call.tone === 'success'
                                                ? 'secondary'
                                                : 'destructive'
                                        }
                                    >
                                        {call.status}
                                    </Badge>
                                    <span className="text-xs text-muted-foreground">
                                        {call.when}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

function Essentials() {
    const { t } = useTrans();
    const { route, has } = useRoute();

    const shortcuts = [
        {
            route: 'panel.profile',
            icon: CircleUser,
            title: 'panel.nav.profile',
            text: 'panel.dashboard.essentials_profile',
        },
        {
            route: 'transaction-password.edit',
            icon: Lock,
            title: 'panel.nav.transaction_password',
            text: 'panel.dashboard.essentials_transaction_password',
        },
        {
            route: 'panel.notifications',
            icon: Bell,
            title: 'panel.nav.notifications',
            text: 'panel.dashboard.essentials_notifications',
        },
    ].filter((shortcut) => has(shortcut.route));

    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-lg font-semibold">
                    {t('panel.dashboard.essentials_title')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t('panel.dashboard.essentials_hint')}
                </p>
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                {shortcuts.map((shortcut) => (
                    <Link
                        key={shortcut.route}
                        href={route(shortcut.route)}
                        className="rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                    >
                        <shortcut.icon className="mb-3 size-5 text-muted-foreground" />
                        <p className="font-medium">{t(shortcut.title)}</p>
                        <p className="text-sm text-muted-foreground">
                            {t(shortcut.text)}
                        </p>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function EmptyState({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="rounded-lg border border-dashed p-6 text-center">
            <p className="font-medium">{title}</p>
            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'panel.nav.dashboard', route: 'dashboard' }],
};
