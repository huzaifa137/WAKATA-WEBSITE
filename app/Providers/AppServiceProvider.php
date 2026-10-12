<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
    }
}
