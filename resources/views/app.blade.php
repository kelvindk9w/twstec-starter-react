<!DOCTYPE html>
{{-- Documento raiz das páginas React (Inertia). O tema da CONTA (quando há
     sessão) vai em data-theme-default; o do dispositivo (localStorage) vence.
     O script abaixo aplica a classe `dark` ANTES da primeira pintura — sem
     flash de tema errado. É o único script inline da página (a CSP do kit
     permite script inline, nunca `unsafe-eval`; ver config/security.php). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-default="{{ in_array(auth()->user()?->theme, ['light', 'dark', 'system'], true) ? auth()->user()->theme : 'system' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script>
            (function () {
                var stored = null;
                try { stored = localStorage.getItem('theme'); } catch (e) { /* storage indisponível */ }
                var pref = (stored === 'light' || stored === 'dark' || stored === 'system')
                    ? stored
                    : (document.documentElement.dataset.themeDefault || 'system');
                var dark = pref === 'dark'
                    || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            })();
        </script>

        {{-- Fundo do documento no tema certo antes do CSS do build carregar. --}}
        <style>
            html { background-color: oklch(1 0 0); }
            html.dark { background-color: oklch(0.145 0 0); }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ platform()->name }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
