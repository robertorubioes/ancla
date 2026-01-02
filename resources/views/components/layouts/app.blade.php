<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    
    <title>{{ config('app.name', 'Firmalum') }} - {{ $title ?? 'Admin Panel' }}</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Livewire Styles -->
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen">
        {{-- Header --}}
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <a href="/" class="flex items-center">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span class="ml-2 text-xl font-bold text-gray-900">{{ config('app.name', 'Firmalum') }}</span>
                        @auth
                            @if(auth()->user()->role->value === 'super_admin')
                                <span class="ml-2 px-2 py-1 text-xs font-semibold bg-purple-100 text-purple-800 rounded-full">Superadmin</span>
                            @endif
                        @endauth
                    </a>
                    <nav class="flex items-center space-x-4">
                        @auth
                            @if(auth()->user()->role->value === 'super_admin')
                                <a href="{{ route('admin.tenants') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                                    Tenants
                                </a>
                                <a href="{{ route('admin.tenants') }}?action=create" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg shadow-sm transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    New Tenant
                                </a>
                            @endif
                            @if(auth()->user()->role->value === 'admin' && auth()->user()->tenant_id)
                                <a href="{{ route('settings.users') }}" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                                    Users
                                </a>
                            @endif
                            <a href="/signing-processes" class="text-sm text-gray-600 hover:text-gray-900">
                                Dashboard
                            </a>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                                <form method="POST" action="{{ route('logout') }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-sm text-red-600 hover:text-red-700">
                                        Logout
                                    </button>
                                </form>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                Sign In
                            </a>
                        @endauth
                    </nav>
                </div>
            </div>
        </header>
        
        {{-- Main Content --}}
        <main class="min-h-screen">
            {{ $slot }}
        </main>
        
        {{-- Footer --}}
        <footer class="bg-white border-t mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="text-center">
                    <p class="text-sm text-gray-400">
                        &copy; {{ date('Y') }} {{ config('app.name', 'Firmalum') }}. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Livewire Scripts -->
    @livewireScripts
</body>
</html>
