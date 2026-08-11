<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class EnsureSuperAdmin {
    public function handle(Request $request, Closure $next): Response {
        $u = $request->user();
        if(!$u){ abort(401); }
        $is = false;
        try {
            if(property_exists($u,'role') && $u->role === 'super_admin') $is = true;
            elseif(method_exists($u,'hasRole')) $is = $u->hasRole('super_admin');
            elseif($u->id === 1) $is = true; // dev fallback
        } catch(\Throwable $e) { $is = ($u->id === 1); }
        if(!$is){ abort(403, 'super_admin_required'); }
        return $next($request);
    }
}
