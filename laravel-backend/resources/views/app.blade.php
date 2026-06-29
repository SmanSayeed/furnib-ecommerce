<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Google Tag Manager (admin) — same container the marketer manages for
             the storefront. The id is format-checked before interpolation so a
             malformed settings value can never inject script. Only the public
             container id reaches the browser (the CAPI token stays server-side). --}}
        @php
            $gtmId = null;
            try {
                $candidate = app(\App\Services\Settings\SettingsService::class)->get('marketing', 'gtm_id');
                if (is_string($candidate) && preg_match('/^GTM-[A-Z0-9]+$/', $candidate)) {
                    $gtmId = $candidate;
                }
            } catch (\Throwable) {
                $gtmId = null;
            }
        @endphp
        @if ($gtmId)
            <script>
                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtmId }}');
            </script>
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
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

        <link rel="icon" href="/logo/furnib-favicon.png" type="image/png" sizes="any">
        <link rel="apple-touch-icon" href="/logo/furnib-favicon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        @if ($gtmId)
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0" width="0" style="display:none;visibility:hidden" title="gtm"></iframe></noscript>
        @endif
        <x-inertia::app />
    </body>
</html>
