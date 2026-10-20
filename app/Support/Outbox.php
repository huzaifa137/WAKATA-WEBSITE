<?php

namespace App\Support;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

use function Illuminate\Support\defer;

/**
 * Sends the emails that must never slow a customer down or fail their order (order received, order updates).
 * On the website the email goes out AFTER the response has reached the browser, in the same PHP process, so no queue
 * worker is needed. In the console (commands, tests) it goes out at once. A mail problem is reported to the log and never thrown.
 *
 * Mail the person is waiting to read right now (sign-in codes, password resets) is not sent here: those are sent
 * straight away so the page can say when it did not work.
 */
class Outbox
{
    public static function send(string|array $to, Mailable $mail): void
    {
        $run = function () use ($to, $mail) {
            try {
                Mail::to($to)->send($mail);
            } catch (\Throwable $e) {
                report($e);
            }
        };

        app()->runningInConsole() ? $run() : defer($run);
    }
}
