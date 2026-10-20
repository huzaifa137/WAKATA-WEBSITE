<?php

namespace App\Jobs;

use App\Mail\GeneralMail;
use App\Models\Newsletter;
use App\Models\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one newsletter to a list of subscribers and writes the counts on its history line.
 * Big lists run this in the background so the Subscribers page does not wait for hundreds of emails.
 */
class SendNewsletter implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @param array<int, int> $subscriberIds */
    public function __construct(public int $newsletterId, public array $subscriberIds, public string $subject, public string $body)
    {
    }

    public function handle(): void
    {
        @set_time_limit(0);
        $ok = $failed = 0;

        Subscriber::whereIn('id', $this->subscriberIds)->where('status', 'active')->orderBy('id')->chunkById(100, function ($chunk) use (&$ok, &$failed) {
            foreach ($chunk as $s) {
                try {
                    Mail::to($s->email)->send(new GeneralMail($this->subject, $this->body, null, null, null, null, route('newsletter.unsubscribe', $s->token)));
                    $ok++;
                } catch (\Throwable $e) {
                    report($e);
                    $failed++;
                }
            }
        });

        Newsletter::whereKey($this->newsletterId)->update(['sent_count' => $ok, 'failed_count' => $failed, 'status' => 'sent']);
    }
}
