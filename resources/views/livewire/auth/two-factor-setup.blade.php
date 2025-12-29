<div>
    <div class="max-w-xl mx-auto">
        <h3 class="text-lg font-medium text-gray-900">
            {{ __('Two-Factor Authentication') }}
        </h3>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Add additional security to your account using two factor authentication.') }}
        </p>

        <div class="mt-5">
            @if ($this->twoFactorEnabled)
                <!-- 2FA Enabled -->
                <div class="p-4 bg-green-50 border border-green-200 rounded-md mb-4">
                    <div class="flex">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <p class="ml-3 text-sm font-medium text-green-800">
                            {{ __('Two-factor authentication is enabled.') }}
                        </p>
                    </div>
                </div>

                @if ($showingRecoveryCodes)
                    <!-- Recovery Codes -->
                    <div class="mt-4">
                        <p class="text-sm text-gray-600 mb-3">
                            {{ __('Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two factor authentication device is lost.') }}
                        </p>

                        <div class="grid gap-1 max-w-xl p-4 font-mono text-sm bg-gray-100 rounded-lg">
                            @foreach ($this->recoveryCodes as $code)
                                <div>{{ $code }}</div>
                            @endforeach
                        </div>

                        <div class="mt-4 flex space-x-3">
                            <button type="button"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    wire:click="regenerateRecoveryCodes">
                                {{ __('Regenerate Recovery Codes') }}
                            </button>
                            <button type="button"
                                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                                    wire:click="hideRecoveryCodes">
                                {{ __('Close') }}
                            </button>
                        </div>
                    </div>
                @else
                    <div class="flex space-x-3">
                        <button type="button"
                                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                                wire:click="showRecoveryCodes">
                            {{ __('Show Recovery Codes') }}
                        </button>
                        <button type="button"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                wire:click="disableTwoFactor"
                                wire:confirm="{{ __('Are you sure you want to disable two-factor authentication?') }}">
                            {{ __('Disable 2FA') }}
                        </button>
                    </div>
                @endif

            @elseif ($confirming)
                <!-- Confirming 2FA Setup -->
                <div class="mt-4">
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('To finish enabling two factor authentication, scan the following QR code using your phone\'s authenticator application or enter the setup key and provide the generated OTP code.') }}
                    </p>

                    @if ($this->qrCodeSvg)
                        <div class="mt-4 p-4 bg-white inline-block rounded-lg shadow">
                            {!! $this->qrCodeSvg !!}
                        </div>
                    @endif

                    @if ($this->setupKey)
                        <div class="mt-4">
                            <p class="text-sm text-gray-600 font-semibold">
                                {{ __('Setup Key') }}: <span class="font-mono">{{ $this->setupKey }}</span>
                            </p>
                        </div>
                    @endif

                    <form wire:submit="confirmTwoFactor" class="mt-4">
                        <div>
                            <label for="confirmationCode" class="block font-medium text-sm text-gray-700">
                                {{ __('Code') }}
                            </label>
                            <input wire:model="confirmationCode" 
                                   id="confirmationCode" 
                                   class="block mt-1 w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('confirmationCode') border-red-300 @enderror" 
                                   type="text" 
                                   inputmode="numeric"
                                   pattern="[0-9]*"
                                   maxlength="6"
                                   name="confirmationCode" 
                                   autofocus 
                                   autocomplete="one-time-code" />
                            @error('confirmationCode')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4 flex space-x-3">
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="confirmTwoFactor">{{ __('Confirm') }}</span>
                                <span wire:loading wire:target="confirmTwoFactor">{{ __('Confirming...') }}</span>
                            </button>
                            <button type="button"
                                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                                    wire:click="disableTwoFactor">
                                {{ __('Cancel') }}
                            </button>
                        </div>
                    </form>
                </div>

            @else
                <!-- Enable 2FA -->
                <p class="text-sm text-gray-600 mb-4">
                    {{ __('When two factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone\'s Google Authenticator application.') }}
                </p>

                <button type="button"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        wire:click="enableTwoFactor"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="enableTwoFactor">{{ __('Enable Two-Factor Authentication') }}</span>
                    <span wire:loading wire:target="enableTwoFactor">{{ __('Enabling...') }}</span>
                </button>
            @endif
        </div>
    </div>
</div>
