import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';

/**
 * Página inicial do PRODUTO (rota `home`): o nome da plataforma e o caminho
 * para entrar (ou voltar ao painel) — o mesmo conteúdo da página mínima do
 * starter Livewire. Sem demonstração.
 */
export default function Welcome() {
    const { app, auth } = usePage().props;
    const { t } = useTrans();
    const { route } = useRoute();

    return (
        <>
            <Head title={app.name} />
            <div className="flex min-h-svh flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-6xl items-center gap-2 px-4 py-4">
                    <Link
                        href={route('home')}
                        className="flex items-center gap-2 font-semibold"
                    >
                        {app.logoUrl ? (
                            <img src={app.logoUrl} alt="" className="size-7" />
                        ) : (
                            <AppLogoIcon className="size-7" />
                        )}
                        <span>{app.name}</span>
                    </Link>
                    <div className="ml-auto flex items-center gap-1">
                        <LocaleSwitcher />
                        <ThemeToggle />
                    </div>
                </header>

                <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col items-start justify-center gap-6 px-4 py-24">
                    <h1 className="text-4xl font-semibold tracking-tight">
                        {app.name}
                    </h1>
                    <p className="max-w-xl text-lg text-muted-foreground">
                        {t('landing.footer.tagline')}
                    </p>
                    <div className="flex flex-wrap gap-3">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={route('dashboard')}>
                                    {t('panel.nav.dashboard')}
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild>
                                    <Link href={route('register')}>
                                        {t('landing.nav.register')}
                                    </Link>
                                </Button>
                                <Button asChild variant="secondary">
                                    <Link href={route('login')}>
                                        {t('landing.nav.login')}
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
