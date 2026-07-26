<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابة كلمة مرور مشتركة: تمنع الوصول لأي صفحة قبل إدخال كلمة المرور.
 */
class SiteGate
{
    /**
     * زواحف معاينة الروابط (link unfurlers). تُقدَّم لها صفحة البوابة بحالة 200
     * بدل التحويل 302، حتى تلتقط وسوم OG من الرابط الأساسي مباشرةً — بعض الزواحف
     * (تيمز/سلاك/آوتلوك) لا تتبع التحويلة جيداً وتخزّن نسخة قديمة تحت الرابط الأصلي.
     * تُعرض صفحة البوابة فقط (نموذج كلمة المرور + الوسوم) فلا يُكشف أي محتوى محمي.
     */
    private const PREVIEW_BOTS = [
        'facebookexternalhit', 'facebot', 'twitterbot', 'slackbot', 'slack-imgproxy',
        'linkedinbot', 'telegrambot', 'discordbot', 'whatsapp', 'skypeuripreview',
        'skype', 'microsoft', 'teams', 'office', 'applebot', 'googlebot', 'bingbot',
        'pinterest', 'redditbot', 'vkshare', 'embedly', 'iframely', 'mastodon',
        'google-inspectiontool', 'duckduckbot', 'yandex', 'qwantify',
    ];

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

            // زواحف معاينة الروابط: صفحة البوابة (200) بدل 302 لالتقاط وسوم OG من الرابط الأساسي
            if ($this->isPreviewBot($request)) {
                return Inertia::render('Gate')->toResponse($request);
            }

            return redirect()->guest(route('gate.show'));
        }

        return $next($request);
    }

    /** هل الطلب من زاحف معاينة روابط معروف؟ (مطابقة User-Agent) */
    private function isPreviewBot(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') {
            return false;
        }

        foreach (self::PREVIEW_BOTS as $bot) {
            if (str_contains($ua, $bot)) {
                return true;
            }
        }

        return false;
    }
}
