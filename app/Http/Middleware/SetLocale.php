<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. فحص إذا كان العميل يرسل لغة محددة في الـ Header (مفيد جداً للـ APIs والتطبيقات)
        // 2. إذا لم يرسلها، يجلب لغة المتصفح الافتراضية للجهاز عبر getPreferredLanguage
        // 3. إذا فشل كل شيء، يختار اللغة الافتراضية من إعدادات لارافل (ar)
        $locale = $request->header('Accept-Language')
            ?? $request->getPreferredLanguage(['ar', 'en'])
            ?? config('app.locale', 'ar');

        // تنظيف القيمة (لأن بعض المتصفحات ترسلها مثل ar-EG، فنأخذ أول حرفين فقط)
        $locale = substr($locale, 0, 2);

        // التأكد من أن اللغة المدعومة هي إما ar أو en فقط
        if (! in_array($locale, ['ar', 'en'])) {
            $locale = config('app.locale', 'ar');
        }

        // تطبيق اللغة في النظام
        App::setLocale($locale);

        return $next($request);
    }
}
