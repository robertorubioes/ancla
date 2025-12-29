<div>
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">
        {{ __('Reset Password') }}
    </h2>

    <form wire:submit="resetPassword">
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

        <!-- Password -->
        <div class="mt-4">
            <label for="password" class="block font-medium text-sm text-gray-700">
                {{ __('New Password') }}
            </label>
            <input wire:model="password" 
                   id="password" 
                   class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('password') border-red-300 @enderror" 
                   type="password" 
                   name="password" 
                   required 
                   autocomplete="new-password" />
            @error('password')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-gray-500">
                {{ __('Minimum 8 characters with uppercase, lowercase, number, and symbol') }}
            </p>
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <label for="password_confirmation" class="block font-medium text-sm text-gray-700">
                {{ __('Confirm Password') }}
            </label>
            <input wire:model="password_confirmation" 
                   id="password_confirmation" 
                   class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                   type="password" 
                   name="password_confirmation" 
                   required 
                   autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <button type="submit" 
                    class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="resetPassword">{{ __('Reset Password') }}</span>
                <span wire:loading wire:target="resetPassword">{{ __('Resetting...') }}</span>
            </button>
        </div>
    </form>
</div>
