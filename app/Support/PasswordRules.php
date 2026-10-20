<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Module 11: one place for "how strong must a password be".
 *  - team members (admin console): at least 10 characters with a letter and a number
 *  - customers: at least 8 characters
 * On the live site both are also checked against the public list of leaked passwords (haveibeenpwned.com, only the
 * first 5 characters of a hash are sent). If that service cannot be reached the password is accepted.
 */
class PasswordRules
{
    public static function admin(): Password
    {
        $rule = Password::min(10)->letters()->numbers();

        return app()->isProduction() ? $rule->uncompromised() : $rule;
    }

    public static function customer(): Password
    {
        $rule = Password::min(8);

        return app()->isProduction() ? $rule->uncompromised() : $rule;
    }
}
