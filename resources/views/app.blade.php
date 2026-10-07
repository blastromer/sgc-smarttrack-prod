<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon-32.png?v=20261005" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon.ico?v=20261005" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=20261005">
        <meta name="theme-color" content="#0b141c">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|source-sans-3:400,600,700|atkinson-hyperlegible:400,700" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
        <script>
            (function () {
                var u = @json($sgcAppearance);
                try {
                    var m = document.cookie.match(/(?:^|; )sgc_ui=([^;]*)/);
                    if (m) {
                        var mine = JSON.parse(decodeURIComponent(m[1]));
                        if (mine && typeof mine === 'object') {
                            u = Object.assign({}, u, mine);
                        }
                    }
                } catch (e) {}
                var root = document.documentElement;
                var fonts = {
                    segoe: "'Segoe UI', Inter, system-ui, sans-serif",
                    source: "'Source Sans 3', 'Segoe UI', sans-serif",
                    atkinson: "'Atkinson Hyperlegible', 'Segoe UI', sans-serif",
                    georgia: "Georgia, 'Times New Roman', serif"
                };
                var sizes = { sm: '14px', md: '16px', lg: '18px', xl: '20px' };
                root.setAttribute('data-theme', u.theme || 'night');
                root.setAttribute('data-density', u.density || 'comfortable');
                root.style.setProperty('--sgc-teal', u.accent || '#2aa7a0');
                var family = u.font_family || fonts[u.font] || fonts.segoe;
                root.style.setProperty('--sgc-font', family);
                root.style.fontFamily = family;
                root.style.fontSize = u.font_size || sizes[u.text_size] || '16px';
            })();
        </script>
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
