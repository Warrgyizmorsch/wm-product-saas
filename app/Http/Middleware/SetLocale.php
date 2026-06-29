<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = array_keys(config('localization.supported', []));
        $fallbackLocale = config('app.fallback_locale', 'en');
        $locale = $request->session()->get('locale', config('app.locale', $fallbackLocale));

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = $fallbackLocale;
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        View::share('currentLocale', $locale);
        View::share('currentLanguage', config("localization.supported.{$locale}"));
        View::share('supportedLanguages', config('localization.supported', []));

        return $next($request);
    }
}
