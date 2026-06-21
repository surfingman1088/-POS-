<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Force staff to change password on first login.
     * Livewire AJAX requests must be allowed through,
     * otherwise the password form submission will be intercepted.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->must_change_password) {
            // Allow Livewire AJAX requests through
            if ($request->hasHeader('X-Livewire') || str_contains($request->path(), 'livewire')) {
                return $next($request);
            }

            // Allow password settings page and logout route
            $allowedRoutes = ['settings.password', 'logout'];
            if (! $request->routeIs(...$allowedRoutes)) {
                return redirect()->route('settings.password')
                    ->with('must_change_password', true);
            }
        }

        return $next($request);
    }
}
