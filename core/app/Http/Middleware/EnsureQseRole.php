<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Penegakan peran §19: user < curator < admin. Pakai: middleware('qse.role:curator'). */
class EnsureQseRole
{
    private const HIERARCHY = ['user' => 0, 'curator' => 1, 'admin' => 2];

    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $role = $request->user()?->role ?? 'user';

        if ((self::HIERARCHY[$role] ?? -1) < (self::HIERARCHY[$minimumRole] ?? 99)) {
            abort(403, "Memerlukan peran minimal: {$minimumRole} (Manifest §19).");
        }

        return $next($request);
    }
}
