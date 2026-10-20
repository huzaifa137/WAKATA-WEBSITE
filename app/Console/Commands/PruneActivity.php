<?php

namespace App\Console\Commands;

use App\Models\AdminActivity;
use Illuminate\Console\Command;

/**
 * Module 11: the activity log is kept for a year by default (ACTIVITY_KEEP_DAYS in .env), then the oldest lines are deleted,
 * so the table does not grow for ever. Scheduled weekly.
 */
class PruneActivity extends Command
{
    protected $signature   = 'wakata:prune-activity {--days= : keep this many days (default: config wakata.activity_keep_days)}';
    protected $description = 'Delete activity log lines older than the keep period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('wakata.activity_keep_days', 365));

        if ($days < 30) {
            $this->error('The log must be kept for at least 30 days.');

            return self::FAILURE;
        }

        $deleted = AdminActivity::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted $deleted activity line(s) older than $days days.");

        return self::SUCCESS;
    }
}
