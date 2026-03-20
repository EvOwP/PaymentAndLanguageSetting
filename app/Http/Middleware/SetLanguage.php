<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use App\Models\Language;

class SetLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Session::has('locale')) {
            $locale = Session::get('locale');
            App::setLocale($locale);
        } else {
            try {
                $defaultLanguage = Language::where('is_default', true)->first();
                if ($defaultLanguage) {
                    App::setLocale($defaultLanguage->code);
                    Session::put('locale', $defaultLanguage->code);
                } else {
                    App::setLocale('en');
                    Session::put('locale', 'en');
                }
            } catch (\Exception $e) {
                // Ignore if DB not migrated
                App::setLocale('en');
            }
        }

        return $next($request);
    }
}
