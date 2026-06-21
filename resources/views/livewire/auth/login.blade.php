<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\System\AuditLogsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth', ['title' => 'Log In'])] class extends Component {
    // #[Validate('required|string|email')]
    // public string $email = '';

    #[Validate('required|string')]
    public string $username = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(AuditLogsService $auditLogsService): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt([
            'username' => ucwords($this->username),
            'password' => $this->password
        ],
            false)) {

                RateLimiter::hit($this->throttleKey());

            $auditLogsService->record('auth.failed_login', null, null, [], [
                'username' => $this->username,
                'ip_address' => request()->ip(),
                'device_type' => 'unknown',
            ], request());

            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
                'password' => __('auth.password'),
                // 'email' => __('auth.email'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        // Clear any stale remember-device token before optionally issuing a new one.
        $auditLogsService->revokeTemporaryDeviceToken(request());
        $auditLogsService->recordLogin(Auth::user(), request());

        // 員工帳號自動記住裝置；管理員依「記住我」勾選決定
        $user = Auth::user();
        $shouldRemember = $this->remember || ($user->role === 'staff');

        if ($shouldRemember) {
            $auditLogsService->issueTemporaryDeviceToken($user, request());
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->username).'|'.request()->ip());
    }
};
?>

<div class="flex flex-col gap-2">

    <div class="text-center">
        <div onclick="window.location.href='/'"  class="cursor-pointer app-bg-gradient rounded-xl p-6 mb-1">
            @if (env('STORE_NAME_ALT'))
                <h1 class="text-5xl font-bold app-text tracking-wider">
                    {{ env('STORE_NAME_ALT') }}
                </h1>

                <p class="app-text text-xl font-semibold">
                    {{ env('STORE_NAME') }}
                </p>

                <small class="app-text">
                    {{ env('STORE_ADDRESS') }}
                </small>

            @else
                <h1 class="text-5xl font-bold app-text tracking-wider">
                    {{ env('STORE_NAME') }}
                </h1>
                <small class="app-text">
                    {{ env('STORE_ADDRESS') }}
                </small>
            @endif
        </div>
    </div>

    <x-auth-header :title="__('Welcome Back')" :description="__('Please enter your email and password below to log in')" />

    {{-- Session Status --}}
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" wire:submit="login" class="flex flex-col gap-6">

        {{-- Username --}}
        <flux:input
            wire:model="username"
            :label="__('Username')"
            type="text"
            required
            autofocus
            autocomplete="username"
            placeholder="{{ __('Please Enter Your Username') }}"
        />

        {{-- Password --}}
        <flux:input
            wire:model="password"
            :label="__('Password')"
            type="password"
            required
            placeholder="{{ __('Please Enter Your Password') }}"
            autocomplete="current-password"
            viewable
        />

        {{-- Remember Me & Forgot Password --}}
        <div class="relative">
            <flux:checkbox wire:model="remember" :label="__('Remember me')" class="cursor-pointer"/>
            <p class="text-xs text-zinc-400 mt-1">{{ __("Staff accounts are remembered automatically.") }}</p>
            @if (Route::has('password.request'))
                <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                    {{ __('Forgot your password?') }}
                </flux:link>
            @endif
        </div>

        {{-- submit button --}}
        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full cursor-pointer app-btn-alt">{{ __('Log in') }}</flux:button>
        </div>
    </form>

    @if (Route::has('register'))
        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Register') }}</flux:link>
        </div>
    @endif

    {{-- Full-screen loading overlay for login submission --}}
    @include('livewire.partials.loading-overlay', ['wireTarget' => 'login'])
</div>
