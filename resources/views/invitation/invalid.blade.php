<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Invitation - Firmalum</title>
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
            <div class="bg-red-50 border-b-4 border-red-500 px-8 py-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="bg-red-100 rounded-full p-3">
                        <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
                <h2 class="text-2xl font-bold text-center text-gray-900">Invalid Invitation</h2>
            </div>

            <!-- Content -->
            <div class="px-8 py-6">
                <div class="text-center space-y-4">
                    <p class="text-gray-700">
                        This invitation link is invalid or has expired.
                    </p>

                    <div class="bg-gray-50 rounded-lg p-4 text-left">
                        <p class="text-sm text-gray-600 mb-2">Possible reasons:</p>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• The invitation has already been accepted</li>
                            <li>• The invitation has expired (invitations are valid for 7 days)</li>
                            <li>• The invitation was cancelled by the organization</li>
                            <li>• The invitation link is incorrect</li>
                        </ul>
                    </div>

                    <p class="text-sm text-gray-600 mt-6">
                        Please contact the person who invited you to request a new invitation.
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="px-8 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex flex-col sm:flex-row gap-3">
                    <a
                        href="{{ route('login') }}"
                        class="flex-1 text-center bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold py-3 px-4 rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all"
                    >
                        Go to Login
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-600">
            <p>&copy; {{ date('Y') }} Firmalum. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
