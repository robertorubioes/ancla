<div>
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">
        {{ __('Sign in to your account') }}
    </h2>

    <form wire:submit.prevent="login" method="POST">
        <!-- Email Address -->
        <div>
            <label for="email" class="block font-medium text-sm text-gray-700">
                {{ __('Email') }}
            </label>
            <input wire:model="email" 
                   id="email" 
                   class="block mt-1 w-full py-2 rounded-lg border border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('email') border-red-300 @enderror" 
                   type="email" 
                   name="email" 
                   required 
                   autofocus 
                   autocomplete="username" />
            @error('email')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div class="mt-4">
            <label for="password" class="block font-medium text-sm text-gray-700">
                {{ __('Password') }}
            </label>
            <input wire:model="password" 
                   id="password" 
                   class="block mt-1 w-full py-2 rounded-lg border border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('password') border-red-300 @enderror" 
                   type="password" 
                   name="password" 
                   required 
                   autocomplete="current-password" />
            @error('password')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="remember" 
                       id="remember" 
                       type="checkbox" 
                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" 
                       name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
               href="{{ route('password.request') }}" 
               wire:navigate>
                {{ __('Forgot your password?') }}
            </a>

            <button type="submit" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>
                <span wire:loading wire:target="login">{{ __('Signing in...') }}</span>
            </button>
        </div>
    </form>

    @if (Route::has('register'))
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                {{ __("Don't have an account?") }}
                <a href="{{ route('register') }}" 
                   class="font-medium text-blue-600 hover:text-blue-500"
                   wire:navigate>
                    {{ __('Sign up') }}
                </a>
            </p>
        </div>
    @endif
</div>
