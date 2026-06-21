<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * 若員工帳號標記為必須修改密碼，強制導向密碼設定頁面。
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->must_change_password) {
            // 允許存取密碼設定頁、登出路由，避免無限重導
            $allowedRoutes = ['settings.password', 'logout'];
            if (! $request->routeIs(...$allowedRoutes)) {
                return redirect()->route('settings.password')
                    ->with('must_change_password', true);
            }
        }

        return $next($request);
    }
}
