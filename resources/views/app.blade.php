<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="{{ \App\Support\Venue::cssVariables() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#1f6f4a">
        <meta name="description" content="{{ config('venue.name') }} — {{ config('venue.tagline') }}">

        <title inertia>{{ config('venue.name', config('app.name')) }}</title>

        <!-- Fonts: bunny.net mirrors Google Fonts with no request logging, and
             serves from a CDN edge close to PH — worth it on a phone booking
             a court over mobile data. -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space-grotesk:500,600,700|inter:400,500,600|jetbrains-mono:500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
