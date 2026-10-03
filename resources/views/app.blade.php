<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        {{-- Etapas 8: nonce – be jo saugos antraštė (CSP, SecurityHeaders) šio skripto nevykdytų --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            @if (is_array($page['props']['seo'] ?? null))
                {{-- Katalogo SEO žymos jau pirmame HTML (robotams ir nuorodų peržiūroms be JavaScript).
                     Tie patys data-inertia raktai kaip SeoHead.vue head-key, todėl Vue jas perima, o ne dubliuoja. --}}
                @php($seo = $page['props']['seo'])
                <title>{{ $seo['title'] }} - {{ config('app.name') }}</title>
                <meta name="description" content="{{ $seo['description'] }}" data-inertia="description">
                <meta name="robots" content="{{ $seo['robots'] }}" data-inertia="robots">
                <link rel="canonical" href="{{ $seo['canonical'] }}" data-inertia="canonical">
                <meta property="og:title" content="{{ $seo['title'] }}" data-inertia="og:title">
                <meta property="og:description" content="{{ $seo['description'] }}" data-inertia="og:description">
                <meta property="og:url" content="{{ $seo['canonical'] }}" data-inertia="og:url">
                {{-- Etapas 8: schema.org JSON-LD. Tekstas jau užkoduotas serveryje (SeoMeta, JSON_HEX_TAG) --}}
                @if (! empty($seo['json_ld']))
                    <script type="application/ld+json" data-inertia="json-ld">{!! $seo['json_ld'] !!}</script>
                @endif
            @else
                <title>{{ config('app.name', 'Laravel') }}</title>
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
