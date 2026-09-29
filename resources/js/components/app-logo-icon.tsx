import type { SVGAttributes } from 'react';

/**
 * A marca do kit (a mesma do starter Livewire e do /admin — public/img/
 * brand-mark.svg): monocromática, herda a cor do texto (currentColor). O
 * logotipo do .env (PLATFORM_LOGO_URL), quando existe, entra no lugar dela.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
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
            <path
                d="M9 11h14M16 11v11"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
        </svg>
    );
}
