<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation - Firmalum</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-purple-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Logo -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-indigo-600">
                Firmalum
            </h1>
            <p class="text-gray-600 mt-2">Electronic Signature Platform</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-8 py-6 text-white">
                <h2 class="text-2xl font-bold">You're Invited! 🎉</h2>
                <p class="mt-2 opacity-90">Complete your registration to join {{ $invitation->tenant->name }}</p>
            </div>

            <!-- Content -->
            <div class="px-8 py-6">
                @if(session('error'))
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        {{ session('error') }}
                    </div>
                @endif

                <!-- Invitation Details -->
                <div class="mb-6 bg-gray-50 rounded-lg p-4">
                    <div class="space-y-3">
                        <div>
                            <span class="text-sm text-gray-500">Organization:</span>
                            <p class="font-semibold text-gray-900">{{ $invitation->tenant->name }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">Your Email:</span>
                            <p class="font-semibold text-gray-900">{{ $invitation->email }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">Your Role:</span>
                            <p>
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-{{ $invitation->role_badge_color }}-100 text-{{ $invitation->role_badge_color }}-800">
                                    {{ $invitation->role->label() }}
                                </span>
                            </p>
                        </div>
                        @if($invitation->message)
                            <div>
                                <span class="text-sm text-gray-500">Message from {{ $invitation->invitedBy->name }}:</span>
                                <p class="text-gray-700 italic mt-1">"{{ $invitation->message }}"</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Registration Form -->
                <form method="POST" action="{{ route('invitation.accept', $token) }}">
                    @csrf

                    <div class="space-y-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                Create Password
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('password') border-red-500 @enderror"
                                placeholder="Enter a secure password"
                            >
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                                Confirm Password
                            </label>
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                placeholder="Confirm your password"
                            >
                        </div>

                        <!-- Password Requirements -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm font-medium text-blue-900 mb-2">Password Requirements:</p>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• At least 8 characters long</li>
                                <li>• Contains uppercase and lowercase letters</li>
                                <li>• Contains at least one number</li>
                                <li>• Contains at least one special character</li>
                            </ul>
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold py-3 px-4 rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all shadow-md"
                        >
                            Create Account & Join
                        </button>
                    </div>
                </form>

                <!-- Expiration Notice -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-500">
                        This invitation expires {{ $invitation->expires_at->diffForHumans() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-600">
            <p>Already have an account? <a href="{{ route('login') }}" class="text-purple-600 hover:text-purple-700 font-semibold">Sign in</a></p>
            <p class="mt-2">&copy; {{ date('Y') }} Firmalum. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
