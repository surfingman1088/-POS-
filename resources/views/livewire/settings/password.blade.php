<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     * 若是首次登入強制修改密碼，不需要輸入舊密碼。
     */
    public function updatePassword(): void
    {
        $user = Auth::user();
        $isForcedChange = $user->must_change_password;

        try {
            if ($isForcedChange) {
                // 首次登入：不需要舊密碼
                $validated = $this->validate([
                    'password' => ['required', 'string', Password::defaults(), 'confirmed'],
                ]);
            } else {
                $validated = $this->validate([
                    'current_password' => ['required', 'string', 'current_password'],
                    'password' => ['required', 'string', Password::defaults(), 'confirmed'],
                ]);
            }
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        $user->update([
            'password'             => Hash::make($validated['password']),
            'must_change_password' => false,  // 清除強制修改標記
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');

        // 若是強制修改，修改完後導向儀表盤
        if ($isForcedChange) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    @if(auth()->user()->must_change_password)
    <div class="mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl">
        <p class="text-sm text-amber-800 dark:text-amber-200 font-medium">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            {{ __('Welcome! For security, please set your own password before continuing.') }}
        </p>
    </div>
    @endif

    <x-settings.layout :heading="__('Update Password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            @if(!auth()->user()->must_change_password)
            <flux:input
                wire:model="current_password"
                :label="__('Current Password')"
                type="password"
                required
                autocomplete="current-password"
            />
            @endif
            <flux:input
                wire:model="password"
                :label="__('New Password')"
                type="password"
                required
                autocomplete="new-password"
            />
            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm Password')"
                type="password"
                required
                autocomplete="new-password"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                @if(!auth()->user()->must_change_password)
                <x-action-message class="me-3" on="password-updated">
                    {{ __('Saved') }}
                </x-action-message>
                @endif
            </div>
        </form>
    </x-settings.layout>
</section>
