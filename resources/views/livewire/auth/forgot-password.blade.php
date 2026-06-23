<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth', ['title' => '找回密碼 | FORGOT PASSWORD'])] class extends Component {
    public string $username = '';
    public bool $submitted = false;

    /**
     * Look up the username and show contact-admin message.
     */
    public function findAccount(): void
    {
        $this->validate([
            'username' => ['required', 'string'],
        ], [
            'username.required' => __('Please enter your username.'),
        ]);

        // Always show the same message regardless of whether the user exists,
        // to avoid leaking account information.
        $this->submitted = true;
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Forgot Password')"
        :description="__('Enter your username to verify your account')"
    />

    @if($submitted)
        {{-- Success / contact-admin message --}}
        <div class="rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-5 text-center space-y-3">
            <div class="flex justify-center">
                <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-800/40 flex items-center justify-center">
                    <i class="fas fa-user-shield text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
            <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">
                {{ __('Please contact your administrator') }}
            </p>
            <p class="text-xs text-blue-600 dark:text-blue-400 leading-relaxed">
                {{ __('Password resets must be done by an administrator. Please reach out to your store manager or system admin to have your password reset.') }}
            </p>
        </div>

        <flux:link :href="route('login')" wire:navigate class="text-center text-sm">
            <i class="fas fa-arrow-left mr-1"></i>{{ __('Back to login') }}
        </flux:link>
    @else
        <form method="POST" wire:submit="findAccount" class="flex flex-col gap-6">
            <flux:input
                wire:model="username"
                :label="__('Username')"
                type="text"
                required
                autofocus
                placeholder="{{ __('Enter your username') }}"
                autocomplete="username"
            />

            @error('username')
                <p class="text-xs text-red-500 -mt-4">
                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                </p>
            @enderror

            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Find My Account') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('Or, return to') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('log in') }}</flux:link>
        </div>
    @endif
</div>
