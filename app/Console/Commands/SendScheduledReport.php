<?php

namespace App\Console\Commands;

use App\Mail\GeneralMail;
use App\Support\ReportExport;
use App\Support\SiteSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Module 10: emails the summary report chosen under Reports > Schedule. The scheduler runs this every morning;
 * it only sends on Mondays (weekly) or the 1st of the month (monthly), and never twice on the same day.
 * Run it by hand with --force to try it.
 */
class SendScheduledReport extends Command
{
    protected $signature   = 'wakata:send-report {--force : send now, whatever the schedule says}';
    protected $description = 'Email the sales summary to the people chosen under Reports, Schedule';

    public function handle(): int
    {
        $s     = ReportExport::schedule();
        $force = (bool) $this->option('force');

        $due = match ($s['frequency']) {
            'weekly'  => now()->isMonday(),
            'monthly' => now()->day === 1,
            default   => false,
        };

        if (! $force && (! $due || $s['last_sent'] === now()->toDateString())) {
            return self::SUCCESS;
        }

        $emails = SiteSettings::emails($s['recipients']);
        if (! $emails) {
            $this->warn('No recipients are set for the emailed report.');

            return self::SUCCESS;
        }

        $days = $s['frequency'] === 'monthly' ? 30 : 7;
        $r    = ReportExport::summary($days);

        $body = 'Here is how the shop did in the last ' . $days . " days.\n\n"
            . 'Revenue: UGX ' . number_format($r['revenue']) . "\n"
            . 'Paid orders: ' . $r['orders'] . "\n"
            . 'Average order: UGX ' . number_format($r['average']) . "\n"
            . 'Orders still to work on: ' . $r['open'] . "\n\n"
            . ($r['top'] ? 'Best sellers:' : 'No paid orders in this period.') . "\n\n"
            . 'Open the Reports page in the admin console for the charts and downloads: ' . route('admin.reports');

        try {
            Mail::to($emails)->send(new GeneralMail(
                'WAKATA ' . ($days === 30 ? 'monthly' : 'weekly') . ' report: UGX ' . number_format($r['revenue']),
                $body, 'Your shop report', $r['top'] ?: null,
                $r['top'] ? [['Revenue', 'UGX ' . number_format($r['revenue'])], ['Paid orders', (string) $r['orders']]] : null,
            ));
        } catch (\Throwable $e) {
            report($e);
            $this->error('The report could not be sent: ' . $e->getMessage());

            return self::FAILURE;
        }

        ReportExport::markSent();
        $this->info('Report sent to ' . implode(', ', $emails));

        return self::SUCCESS;
    }
}
