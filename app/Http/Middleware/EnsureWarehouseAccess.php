<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWarehouseAccess
{
    /**
     * 允許 admin、warehouse、branch 角色存取倉儲模組
     * staff 角色不允許存取
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowedRoles = ['admin', 'warehouse', 'branch'];

        if (! in_array($user->role, $allowedRoles)) {
            abort(403, '您沒有存取倉儲管理的權限。');
        }

        return $next($request);
    }
}
