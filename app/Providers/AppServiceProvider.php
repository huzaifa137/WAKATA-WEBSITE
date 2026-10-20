<?php

namespace App\Providers;

use App\Support\Cart;
use App\Support\CustomerSession;
use App\Support\Portal;
use App\Support\ShopSettings;
use App\Support\SiteContent;
use App\Support\SiteSettings;
use Illuminate\Console\Scheduling\Schedule;
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
        // Delivery names and fees, and the payment methods switched on or off by staff (module 8)
        ShopSettings::apply();
        SiteContent::apply();
        SiteSettings::apply();

        // Module 10: the emailed report (Reports > Schedule). Runs every morning and decides for itself whether today is the day.
        // The server needs the usual cron line:  * * * * * php artisan schedule:run
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('wakata:send-report')->dailyAt('07:00')->withoutOverlapping());
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('wakata:prune-activity')->weeklyOn(0, '03:00')->withoutOverlapping());

        // @c('home.title') prints a text staff can edit under Site content (escaped)
        Blade::directive('c', fn ($expression) => "<?php echo e(\\App\\Support\\SiteContent::get({$expression})); ?>");

        // @icon('grid') / @icon('external', 'sb-ext') render the inline SVGs from config/icons.php
        Blade::directive('icon', fn (string $expression) => "<?php echo \\App\\Support\\Icon::svg({$expression}); ?>");

        // Signed-in customer and cart size for the public pages and the portal: become WK.user and WK.cart in the browser.
        View::composer(['partials.site.wk-globals', 'partials.site.header', 'partials.account.auth-state'], function ($view) {
            $request = request();

            $view->with('wkUser', ($c = CustomerSession::resolve($request)) ? $c->toFrontend() : null);
            $view->with('wkCartCount', $request->hasSession() ? Cart::countFor($request->session()) : 0);
        });

        // The customer portal's own data (orders, files, threads, addresses ...) for the signed-in customer: WK.portal
        View::composer('partials.account.auth-state', function ($view) {
            $request  = request();
            $customer = CustomerSession::resolve($request);

            $view->with('wkPortal', $customer ? Portal::for($customer, $request->attributes->get('portal.focus')) : null);
        });
    }
}
