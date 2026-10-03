<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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
