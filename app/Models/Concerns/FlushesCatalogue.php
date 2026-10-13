<?php

namespace App\Models\Concerns;

use App\Support\Catalogue;

/** Any change to a catalogue record clears the cached front-end payload, so the site never shows stale data. */
trait FlushesCatalogue
{
    protected static function bootFlushesCatalogue(): void
    {
        static::saved(fn () => Catalogue::flush());
        static::deleted(fn () => Catalogue::flush());
    }
}
