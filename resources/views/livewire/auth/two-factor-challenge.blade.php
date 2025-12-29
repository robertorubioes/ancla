<div>
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">
        {{ __('Two-Factor Authentication') }}
    </h2>

    <div class="mb-4 text-sm text-gray-600">
        @if ($useRecoveryCode)
            {{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}
        @else
            {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}
        @endif
    </div>

    <form wire:submit="verify">
        @if ($useRecoveryCode)
            <!-- Recovery Code -->
            <div>
                <label for="recoveryCode" class="block font-medium text-sm text-gray-700">
                    {{ __('Recovery Code') }}
                </label>
                <input wire:model="recoveryCode" 
                       id="recoveryCode" 
                       class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('recoveryCode') border-red-300 @enderror" 
                       type="text" 
                       name="recoveryCode" 
                       autofocus 
                       autocomplete="one-time-code" />
                @error('recoveryCode')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @else
            <!-- Authentication Code -->
            <div>
                <label for="code" class="block font-medium text-sm text-gray-700">
                    {{ __('Code') }}
                </label>
                <input wire:model="code" 
                       id="code" 
                       class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('code') border-red-300 @enderror" 
                       type="text" 
                       inputmode="numeric"
                       pattern="[0-9]*"
                       maxlength="6"
                       name="code" 
                       autofocus 
                       autocomplete="one-time-code" />
                @error('code')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="flex items-center justify-between mt-4">
            <button type="button" 
                    class="text-sm text-gray-600 hover:text-gray-900 underline cursor-pointer"
                    wire:click="toggleRecoveryCode">
                @if ($useRecoveryCode)
                    {{ __('Use an authentication code') }}
                @else
                    {{ __('Use a recovery code') }}
                @endif
            </button>

            <button type="submit" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="verify">{{ __('Verify') }}</span>
                <span wire:loading wire:target="verify">{{ __('Verifying...') }}</span>
            </button>
        </div>
    </form>
</div>
