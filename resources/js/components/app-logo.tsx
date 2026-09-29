import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

/**
 * Marca do painel: o logotipo do .env (PLATFORM_LOGO_URL) quando há um; sem
 * ele, a marca monocromática do kit. O nome é o da plataforma
 * (PLATFORM_NAME) — nada escrito no código.
 */
export default function AppLogo() {
    const { app } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {app.logoUrl ? (
                    <img src={app.logoUrl} alt="" className="size-5" />
                ) : (
                    <AppLogoIcon className="size-5" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {app.name}
                </span>
            </div>
        </>
    );
}
