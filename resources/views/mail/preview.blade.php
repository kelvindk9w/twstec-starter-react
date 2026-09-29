{{-- Galeria de pré-visualização dos e-mails (/mail-preview) — só em dev.

     O controller é do pacote twstec/kit-foundation (MailPreviewController) e
     entrega a view `mail.preview`. No starter React ela é uma página Blade
     AUTOSSUFICIENTE (estilo inline, tema pelo sistema), sem o build do front:
     é ferramenta interna, e os e-mails em si são Blade (layout do foundation),
     iguais nos dois starters. O e-mail aparece num <iframe srcdoc>: o kit
     manda X-Frame-Options: DENY e frame-ancestors 'none', então o e-mail
     carregado por URL não entraria em iframe nenhum; com srcdoc não há
     resposta HTTP a recusar, e o e-mail fica isolado do CSS da página. --}}
@php
    $schemes = ['light', 'dark'];
    $scheme = $dark ? 'dark' : 'light';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('mail.preview.title') }} · {{ platform()->name }}</title>
    <style>
        :root { color-scheme: light dark; --bg: #fafafa; --card: #ffffff; --text: #171717; --muted: #737373; --line: #e5e5e5; --accent: #171717; --accent-text: #fafafa; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0a0a0a; --card: #171717; --text: #fafafa; --muted: #a3a3a3; --line: #262626; --accent: #fafafa; --accent-text: #171717; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.5 ui-sans-serif, system-ui, sans-serif; }
        main { max-width: 72rem; margin: 0 auto; padding: 2.5rem 1rem; }
        h1 { margin: 0; font-size: 1.75rem; letter-spacing: -0.02em; }
        .muted { color: var(--muted); }
        .grid { display: grid; gap: 1.5rem; margin-top: 2rem; }
        @media (min-width: 64rem) { .grid { grid-template-columns: 220px minmax(0, 1fr); } }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 0.75rem; padding: 1rem; }
        .list { list-style: none; margin: 0.75rem 0 0; padding: 0; }
        .list a { display: block; padding: 0.5rem 0.75rem; border-radius: 0.5rem; color: var(--muted); text-decoration: none; }
        .list a[aria-current="page"] { background: var(--line); color: var(--text); font-weight: 600; }
        .bar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1.5rem; }
        .pill { padding: 0.25rem 0.625rem; border-radius: 0.5rem; color: var(--muted); text-decoration: none; font-size: 0.8125rem; }
        .pill[aria-current="true"] { background: var(--accent); color: var(--accent-text); }
        .actions { margin-inline-start: auto; display: flex; gap: 0.5rem; }
        .button { padding: 0.375rem 0.75rem; border: 1px solid var(--line); border-radius: 0.5rem; color: var(--text); text-decoration: none; font-size: 0.8125rem; }
        .label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin: 0; }
        iframe { width: 100%; height: 760px; margin-top: 1rem; border: 1px solid var(--line); border-radius: 0.75rem; background: #fff; }
        pre { white-space: pre-wrap; word-break: break-word; font: 12px/1.6 ui-monospace, monospace; }
    </style>
</head>
<body>
<main>
    <header>
        <h1>{{ __('mail.preview.title') }}</h1>
        <p class="muted">{{ __('mail.preview.subtitle') }}</p>
    </header>

    <div class="grid">
        <nav aria-label="{{ __('mail.preview.list_heading') }}">
            <p class="label">{{ __('mail.preview.list_heading') }}</p>
            <ul class="list">
                @foreach ($slugs as $slug)
                    <li>
                        <a href="{{ route('mail.preview', ['slug' => $slug, 'lang' => $previewLocale, 'scheme' => $scheme]) }}" @if ($slug === $current) aria-current="page" @endif>{{ __('mail.preview.emails.'.$slug) }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div style="min-width: 0">
            <div class="card bar">
                <div>
                    <span class="muted">{{ __('mail.preview.language') }}</span>
                    @foreach (platform()->availableLocales as $locale)
                        <a class="pill" href="{{ route('mail.preview', ['slug' => $current, 'lang' => $locale, 'scheme' => $scheme]) }}" aria-current="{{ $locale === $previewLocale ? 'true' : 'false' }}">{{ __('mail.preview.locales.'.$locale) }}</a>
                    @endforeach
                </div>
                <div>
                    <span class="muted">{{ __('mail.preview.scheme') }}</span>
                    @foreach ($schemes as $option)
                        <a class="pill" href="{{ route('mail.preview', ['slug' => $current, 'lang' => $previewLocale, 'scheme' => $option]) }}" aria-current="{{ $option === $scheme ? 'true' : 'false' }}">{{ __('mail.preview.schemes.'.$option) }}</a>
                    @endforeach
                </div>
                <div class="actions">
                    <a class="button" target="_blank" href="{{ route('mail.preview', ['slug' => $current, 'lang' => $previewLocale, 'scheme' => $scheme, 'format' => 'html']) }}">{{ __('mail.preview.open_html') }}</a>
                    <a class="button" target="_blank" href="{{ route('mail.preview', ['slug' => $current, 'lang' => $previewLocale, 'scheme' => $scheme, 'format' => 'text']) }}">{{ __('mail.preview.open_text') }}</a>
                </div>
            </div>

            <div class="card" style="margin-top: 1rem">
                <p class="label">{{ __('mail.preview.subject') }}</p>
                <p style="margin: 0.25rem 0 0; font-weight: 600">{{ $email['subject'] }}</p>
            </div>

            <iframe title="{{ __('mail.preview.emails.'.$current) }}" srcdoc="{{ $email['html'] }}"></iframe>

            <details class="card" style="margin-top: 1rem">
                <summary style="cursor: pointer; font-weight: 600">{{ __('mail.preview.plain_text') }}</summary>
                <pre>{{ $email['text'] }}</pre>
            </details>

            <p class="muted">{{ __('mail.preview.mailpit_hint') }}</p>
        </div>
    </div>
</main>
</body>
</html>
