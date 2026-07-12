<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Equivalente hex de --bg (oklch(0.16 0.013 48)): pinta a barra do navegador no mobile. --}}
        <meta name="theme-color" content="#120c08">

        {{-- Kena é dark-only: a classe .dark é fixa no <html>, sem toggle de aparência.
             color-scheme faz o navegador pintar scrollbars, <select> nativo e controles
             de formulário no esquema escuro (sem isso ficam claros no Windows). --}}
        <style>
            html,
            html.dark {
                color-scheme: dark;
                background-color: oklch(0.16 0.013 48);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Veludo: Oswald (display) + Hanken Grotesk (corpo) + JetBrains Mono (código) --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Hanken+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
