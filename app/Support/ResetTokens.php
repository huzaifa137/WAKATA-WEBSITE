<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Password-reset tokens for the custom auth (admin and customer share one table). */
class ResetTokens
{
    private const TTL_MINUTES = 60;

    /** @param int $minutes how long the link works (a team invitation lasts days, a reset link an hour) */
    public static function create(string $guard, string $email, int $minutes = self::TTL_MINUTES): string
    {
        $token = Str::random(64);

        // valid() counts TTL_MINUTES from created_at, so a longer life is a created_at set that far into the future
        DB::table('wk_password_resets')->updateOrInsert(
            ['guard' => $guard, 'email' => $email],
            ['token' => hash('sha256', $token), 'created_at' => now()->addMinutes(max(0, $minutes - self::TTL_MINUTES))]
        );

        return $token;
    }

    public static function valid(string $guard, string $email, string $token): bool
    {
        $row = DB::table('wk_password_resets')->where('guard', $guard)->where('email', $email)->first();

        if (! $row || ! hash_equals($row->token, hash('sha256', $token))) {
            return false;
        }

        return Carbon::parse($row->created_at)->addMinutes(self::TTL_MINUTES)->isFuture();
    }

    public static function forget(string $guard, string $email): void
    {
        DB::table('wk_password_resets')->where('guard', $guard)->where('email', $email)->delete();
    }
}
