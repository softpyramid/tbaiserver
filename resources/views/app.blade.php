<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ur', 'ar', 'fa'], true) ? 'rtl' : 'ltr' }}" data-nav-shell="{{ data_get($page, 'props.nav.shell', 'sidebar') }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#14294c">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
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
                background-color: #f8fafc;
            }

            html.dark {
                background-color: #0b1220;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/logo.png" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="{{ route('manifest') }}">

        {{-- Remove noindex and open robots.txt at public launch, after the MoU is signed and Hira approves the copy. --}}
        <meta name="robots" content="noindex, nofollow">
        <meta name="description" content="TB-AI is in development as decision support for tuberculosis microscopy and as a daily TB register. The microscopist makes the final call.">
        <meta property="og:title" content="TB-AI | TB register and decision support">
        <meta property="og:description" content="TB-AI is in development as decision support for tuberculosis microscopy and as a daily TB register. The microscopist makes the final call.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <link rel="canonical" href="{{ url()->current() }}">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>TB-AI | TB register and decision support</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
