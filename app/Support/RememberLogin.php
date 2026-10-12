<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * "Keep me signed in" for the custom sessions.
 * The cookie holds "<id>|<random token>"; the database only keeps the sha256 of the token.
 * One token per account, so signing in with "keep me signed in" on a new device replaces the old one.
 */
class RememberLogin
{
    private const MINUTES = 60 * 24 * 30;

    public static function issue(Model $account, string $cookie): void
    {
        $token = Str::random(60);
        $account->forceFill(['remember_token' => hash('sha256', $token)])->save();

        Cookie::queue(Cookie::make($cookie, $account->getKey() . '|' . $token, self::MINUTES, '/', null, request()->isSecure(), true, false, 'lax'));
    }

    public static function restore(string $modelClass, string $cookie, Request $request): ?Model
    {
        $raw = $request->cookie($cookie);
        if (! is_string($raw) || ! str_contains($raw, '|')) {
            return null;
        }

        [$id, $token] = explode('|', $raw, 2);
        $account = $modelClass::find($id);

        if ($account && $account->remember_token && hash_equals($account->remember_token, hash('sha256', $token))) {
            return $account;
        }

        Cookie::queue(Cookie::forget($cookie));

        return null;
    }

    public static function forget(?Model $account, string $cookie): void
    {
        if ($account) {
            $account->forceFill(['remember_token' => null])->save();
        }
        Cookie::queue(Cookie::forget($cookie));
    }
}
