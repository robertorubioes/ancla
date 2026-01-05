<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <title>{{ config('app.name', 'Firmalum') }} - {{ $title ?? 'Authentication' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Livewire Styles -->
    @livewireStyles
</head>
<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <!-- Logo / Branding -->
        <div class="mb-6">
            <a href="/" wire:navigate>
                @if(isset($tenant) && $tenant?->logo_path)
                    <img src="{{ Storage::url($tenant->logo_path) }}" alt="{{ $tenant->name }}" class="h-16 w-auto">
                @else
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-12 w-12 text-blue-600"><path d="m18 5-3-3H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2"></path><path d="M8 18h1"></path><path d="M18.4 9.6a2 2 0 1 1 3 3L17 17l-4 1 1-4Z"></path></svg>
                        <span class="ml-2 text-2xl font-bold text-gray-900">Firmalum</span>
                    </div>
                @endif
            </a>
        </div>

        <!-- Tenant Name -->
        @if(isset($tenant) && $tenant)
            <div class="mb-4 text-sm text-gray-600">
                {{ $tenant->name }}
            </div>
        @endif

        <!-- Card Container -->
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            <!-- Flash Messages -->
            @if (session('status'))
                <div class="mb-4 font-medium text-sm text-green-600">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 font-medium text-sm text-red-600">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Firmalum') }}. All rights reserved.</p>
            @if(isset($tenant) && $tenant)
                <p class="mt-1">Powered by Firmalum</p>
            @endif
        </div>
    </div>

    <!-- Livewire Scripts -->
    @livewireScriptConfig
</body>
</html>
