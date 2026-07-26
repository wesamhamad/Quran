<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابة كلمة مرور مشتركة: تمنع الوصول لأي صفحة قبل إدخال كلمة المرور.
 */
class SiteGate
{
    public function handle(Request $request, Closure $next): Response
    {
        // مسموح دائماً: صفحة البوابة نفسها + فحص الصحة
        if ($request->is('gate') || $request->is('up')) {
            return $next($request);
        }

        if (! $request->session()->get('site_gate_passed')) {
            // طلبات JSON/الواجهة البرمجية → 401 بدل التحويل
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(401, 'الدخول يتطلب كلمة المرور.');
            }

            return redirect()->guest(route('gate.show'));
        }

        return $next($request);
    }
}
