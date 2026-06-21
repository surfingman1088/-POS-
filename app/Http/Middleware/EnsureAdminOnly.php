<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOnly
{
    /**
     * 僅允許管理員 (admin) 存取，員工 (staff) 將被導向儀表板並顯示無權限提示。
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->role !== 'admin') {
            session()->flash('error', __('Access denied. Admin only.'));
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
