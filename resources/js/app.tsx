import { createInertiaApp } from '@inertiajs/react';
import { FlashToast } from '@/components/flash-toast';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-theme';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';

// Nome da plataforma (PLATFORM_NAME) no título da aba: vem das props do
// servidor, não de uma variável de build.
let appName = '';

void createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            // O link de convite é público: o layout das telas de entrada.
            case name.startsWith('auth/') || name.startsWith('invitations/'):
                return AuthLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app, { page }) {
        appName = page.props.app.name;

        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <FlashToast initial={page.props.flash.status} />
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// Tema claro/escuro/sistema (o <head> já aplicou antes da primeira pintura).
initializeTheme();
