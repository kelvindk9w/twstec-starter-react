import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { ThemeToggle } from '@/components/theme-toggle';
import { useTrans } from '@/lib/i18n';
import { useRoute } from '@/lib/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Cartão central (design do kit oficial) com a marca, o idioma e o tema no
 * topo — entrar não é sair do site: as mesmas saídas do starter Livewire.
 */
export default function AuthSimpleLayout({
    children,
    title = '',
    description = '',
}: AuthLayoutProps) {
    const { t } = useTrans();
    const { route } = useRoute();
    const { app } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col bg-background">
            {title !== '' && <Head title={t(title)} />}
            <div className="flex items-center justify-end gap-1 p-4">
                <LocaleSwitcher />
                <ThemeToggle />
            </div>
            <main className="flex flex-1 flex-col items-center justify-center gap-6 px-6 pb-12 md:px-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4">
                            <Link
                                href={route('home')}
                                className="flex flex-col items-center gap-2 font-medium"
                            >
                                <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                                    {app.logoUrl ? (
                                        <img
                                            src={app.logoUrl}
                                            alt=""
                                            className="size-9"
                                        />
                                    ) : (
                                        <AppLogoIcon className="size-9 text-foreground" />
                                    )}
                                </div>
                                <span className="sr-only">{app.name}</span>
                            </Link>

                            {title !== '' && (
                                <div className="space-y-2 text-center">
                                    <h1 className="text-xl font-medium">
                                        {t(title)}
                                    </h1>
                                    {description !== '' && (
                                        <p className="text-center text-sm text-muted-foreground">
                                            {t(description)}
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}
