<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Stand-in for the admin "payments" screen (module 8): confirm an order's payment from the terminal, so the portal's
 * library, invoices and tracking can be tried with real orders.   php artisan wakata:mark-paid WK-10001 --status=completed
 */
class MarkOrderPaid extends Command
{
    protected $signature = 'wakata:mark-paid {number : Order number, e.g. WK-10001} {--status=confirmed : confirmed|processing|ready|shipped|completed} {--ref= : Payment reference}';

    protected $description = 'Confirm payment for an order (until the admin sales screens exist)';

    public function handle(): int
    {
        $order = Order::where('number', strtoupper($this->argument('number')))->first();
        if (! $order) {
            $this->error('No such order.');

            return self::FAILURE;
        }

        $status = (string) $this->option('status');
        if (! in_array($status, ['confirmed', 'processing', 'ready', 'shipped', 'completed'], true)) {
            $this->error('Status must be confirmed, processing, ready, shipped or completed.');

            return self::FAILURE;
        }

        $order->forceFill(['payment_status' => 'paid', 'status' => $status, 'transaction_ref' => $this->option('ref') ?: $order->transaction_ref])->save();
        $order->record('Payment received', $order->payment_label);
        $order->record(ucfirst($status));

        $this->info("{$order->number} is now paid and {$status}.");

        return self::SUCCESS;
    }
}
