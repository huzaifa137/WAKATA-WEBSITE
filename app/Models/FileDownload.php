<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/** How many times one file of one order has been downloaded. The limit is enforced here, in one atomic statement. */
class FileDownload extends Model
{
    public const DEFAULT_LIMIT = 5;

    protected $fillable = ['order_id', 'customer_id', 'file_key', 'used', 'max_downloads', 'last_downloaded_at', 'last_ip'];

    protected function casts(): array
    {
        return ['used' => 'integer', 'max_downloads' => 'integer', 'last_downloaded_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function limit(): int
    {
        return max(1, (int) config('wakata.download_limit', self::DEFAULT_LIMIT));
    }

    /**
     * Use up one download. Returns the updated row, or null when the limit is already reached.
     * The check and the increment are ONE update statement, so two requests at the same moment can never go past the limit.
     */
    public static function take(Order $order, string $key, ?string $ip = null): ?self
    {
        // insertOrIgnore: two first downloads at the same moment must not collide on the unique key
        self::query()->insertOrIgnore([
            'order_id' => $order->id, 'file_key' => $key, 'customer_id' => $order->customer_id,
            'used' => 0, 'max_downloads' => self::limit(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $taken = self::query()
            ->where('order_id', $order->id)->where('file_key', $key)
            ->whereColumn('used', '<', 'max_downloads')
            ->update(['used' => DB::raw('used + 1'), 'last_downloaded_at' => now(), 'last_ip' => $ip, 'updated_at' => now()]);

        return $taken ? self::where('order_id', $order->id)->where('file_key', $key)->first() : null;
    }

    /** Give a download back (the file could not be delivered after all, e.g. the email failed). */
    public function giveBack(): void
    {
        self::query()->whereKey($this->id)->where('used', '>', 0)->update(['used' => DB::raw('used - 1')]);
    }
}
