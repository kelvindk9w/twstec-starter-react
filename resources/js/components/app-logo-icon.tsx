import { usePage } from '@inertiajs/react';
import type { SVGAttributes } from 'react';

/**
 * A marca de reserva da plataforma (a mesma do starter Livewire e do
 * /admin): a inicial do nome (PLATFORM_NAME) num quadrado, monocromática,
 * herdando a cor do texto (currentColor). Nada do kit — trocar o nome troca a
 * marca. O logotipo do .env (PLATFORM_LOGO_URL), quando existe, entra no
 * lugar dela.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const { app } = usePage().props;

    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            data-brand-initial={app.initial}
        >
            <rect
                x="1"
                y="1"
                width="30"
                height="30"
                rx="8"
                stroke="currentColor"
                strokeWidth="2"
            />
            <text
                x="16"
                y="17"
                fill="currentColor"
                fontFamily="system-ui, -apple-system, Segoe UI, sans-serif"
                fontSize="16"
                fontWeight="700"
                textAnchor="middle"
                dominantBaseline="central"
            >
                {app.initial}
            </text>
        </svg>
    );
}
