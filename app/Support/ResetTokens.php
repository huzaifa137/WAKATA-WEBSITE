<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Password-reset tokens for the custom auth (admin and customer share one table). */
class ResetTokens
{
    private const TTL_MINUTES = 60;

    public static function create(string $guard, string $email): string
    {
        $token = Str::random(64);

        DB::table('wk_password_resets')->updateOrInsert(
            ['guard' => $guard, 'email' => $email],
            ['token' => hash('sha256', $token), 'created_at' => now()]
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
