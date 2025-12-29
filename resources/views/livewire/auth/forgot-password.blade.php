<div>
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">
        {{ __('Forgot your password?') }}
    </h2>

    @if ($emailSent)
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <div class="flex">
                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="ml-3 text-sm font-medium text-green-800">
                    {{ __('If an account with that email exists, we have sent a password reset link.') }}
                </p>
            </div>
        </div>

        <div class="text-center">
            <a href="{{ route('login') }}" 
               class="font-medium text-blue-600 hover:text-blue-500"
               wire:navigate>
                {{ __('Return to login') }}
            </a>
        </div>
    @else
        <div class="mb-4 text-sm text-gray-600">
            {{ __('No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        <form wire:submit="sendResetLink">
            <!-- Email Address -->
            <div>
                <label for="email" class="block font-medium text-sm text-gray-700">
                    {{ __('Email') }}
                </label>
                <input wire:model="email" 
                       id="email" 
                       class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('email') border-red-300 @enderror" 
                       type="email" 
                       name="email" 
                       required 
                       autofocus 
                       autocomplete="username" />
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                   href="{{ route('login') }}"
                   wire:navigate>
                    {{ __('Back to login') }}
                </a>

                <button type="submit" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="sendResetLink">{{ __('Email Password Reset Link') }}</span>
                    <span wire:loading wire:target="sendResetLink">{{ __('Sending...') }}</span>
                </button>
            </div>
        </form>
    @endif
</div>
