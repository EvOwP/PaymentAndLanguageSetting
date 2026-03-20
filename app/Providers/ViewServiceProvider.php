<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\App;
use App\Models\Language;

use App\Models\Setting;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            try {
                $navbarLanguages = Language::where('show_in_navbar', true)->where('is_active', true)->get();
                $currentLang = Language::where('code', App::getLocale())->first();
                $setting = Setting::where('key', 'show_language_options')->first();
                $showLanguageOptions = $setting ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) : true;

                $view->with('navbarLanguages', $navbarLanguages)
                    ->with('currentLang', $currentLang)
                    ->with('showLanguageOptions', $showLanguageOptions);
            } catch (\Exception $e) {
                // If migration is not yet run
                $view->with('navbarLanguages', collect())
                    ->with('currentLang', null)
                    ->with('showLanguageOptions', true);
            }
        });
    }
}
