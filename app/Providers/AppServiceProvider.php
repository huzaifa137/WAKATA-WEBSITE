<?php

namespace App\Providers;

use App\Support\Cart;
use App\Support\CustomerSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @icon('grid') / @icon('external', 'sb-ext') render the inline SVGs from config/icons.php
        Blade::directive('icon', fn (string $expression) => "<?php echo \\App\\Support\\Icon::svg({$expression}); ?>");

        // Signed-in customer and cart size for the public pages and the portal: become WK.user and WK.cart in the browser.
        View::composer(['partials.site.wk-globals', 'partials.site.header', 'partials.account.auth-state'], function ($view) {
            $request = request();

            $view->with('wkUser', ($c = CustomerSession::resolve($request)) ? $c->toFrontend() : null);
            $view->with('wkCartCount', $request->hasSession() ? Cart::countFor($request->session()) : 0);
        });
    }
}
