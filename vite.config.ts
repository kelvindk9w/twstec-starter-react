import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import inertia from '@inertiajs/vite';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, lazyPlugins } from 'vite-plus';

// Tema do /admin (resources/css/filament.css): só com o painel instalado. O
// /admin (twstec/kit-admin, que traz o Filament) é OPCIONAL e é o MESMO do
// starter Livewire — sem ele, o filament.css não tem o que importar e fica
// fora do build.
const admin =
    existsSync(resolve('vendor/filament/filament')) &&
    existsSync(resolve('vendor/twstec/kit-admin'));

// Diferenças para o vite.config.ts do kit oficial:
// - sem o Wayfinder (@laravel/vite-plugin-wayfinder): ele roda `php artisan`
//   durante o build, e aqui o build roda num container só de Node. Os
//   endereços chegam do servidor pelo nome da rota (resources/js/lib/routes.ts);
// - sem a fonte do Bunny Fonts (`fonts:` do laravel-vite-plugin): a CSP do kit
//   não permite fonte externa — a Instrument Sans vem do @fontsource
//   (resources/css/app.css);
// - a entrada do tema do /admin, quando ele está instalado.
export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
                ...(admin ? ['resources/css/filament.css'] : []),
            ],
            refresh: true,
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
                '**/storage/framework/views/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/components/ui/*',
            'tests/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            // A documentação segue o estilo do resto do repositório (docs/).
            '**/*.md',
            'composer.json',
            'composer.lock',
            'package-lock.json',
            'resources/js/components/ui/*',
            'resources/views/**',
            'resources/css/filament.css',
            'resources/css/theme.css',
            'lang/**',
            'vendor/**',
            'public/**',
            'storage/**',
            'tests/**',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
