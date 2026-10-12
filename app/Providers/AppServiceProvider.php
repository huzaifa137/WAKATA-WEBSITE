<?php

namespace App\Providers;

use App\Models\Customer;
use App\Support\RememberLogin;
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

        // Signed-in customer for the public pages and the portal: becomes WK.user in the browser.
        View::composer(['partials.site.wk-globals', 'partials.account.auth-state'], function ($view) {
            $request = request();
            if (! $request->hasSession()) {
                $view->with('wkUser', null);

                return;
            }

            $id = $request->session()->get('LoggedCustomer');
            $customer = $id ? Customer::find($id) : null;

            if (! $customer) {
                $customer = RememberLogin::restore(Customer::class, 'wk_customer_remember', $request);
                if ($customer && $customer->isActive()) {
                    $request->session()->put('LoggedCustomer', $customer->id);
                }
            }

            $view->with('wkUser', $customer && $customer->isActive() ? $customer->toFrontend() : null);
        });
    }
}
