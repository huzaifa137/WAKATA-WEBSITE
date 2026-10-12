<?php

namespace App\Support;

class Icon
{
    /**
     * Inline SVG for an icon defined in config/icons.php.
     * Usage in Blade: @icon('grid')  or  @icon('external', 'sb-ext')
     */
    public static function svg(string $name, string $class = ''): string
    {
        $body = config('icons.' . $name, '');

        return '<svg class="ico ' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true">' . $body . '</svg>';
    }
}
