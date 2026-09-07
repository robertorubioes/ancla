<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    
    <title>{{ config('app.name', 'Firmalum') }} - Firma de Documento</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Livewire Styles -->
    @livewireStyles
    
    <style>
        /* Prevent pull-to-refresh on mobile */
        html, body {
            overscroll-behavior: none;
        }
        
        /* Safe area for notched devices */
        .safe-area-top {
            padding-top: env(safe-area-inset-top, 0);
        }
        
        .safe-area-bottom {
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
    </style>
</head>
<body class="font-sans antialiased">
    {{ $slot }}
    
    <!-- Livewire Scripts -->
    @livewireScripts
</body>
</html>
