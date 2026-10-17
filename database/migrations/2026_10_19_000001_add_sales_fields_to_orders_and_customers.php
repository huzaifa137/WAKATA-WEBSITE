<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 8 (admin sales): what the staff add to an order and to a customer.
 *  - orders: internal note, courier note, when it was paid, whether printed stock was taken for it, where it came from.
 *  - customers: contact person, district and an internal note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('admin_note')->nullable()->after('note');              // "Only your team can see this."
            $table->string('courier_note', 255)->nullable()->after('admin_note'); // courier, rider or tracking note
            $table->timestamp('paid_at')->nullable()->after('transaction_ref');
            $table->timestamp('stock_deducted_at')->nullable()->after('paid_at'); // printed stock has been taken for this order
            $table->string('source', 12)->default('online')->after('stock_deducted_at'); // online | manual (phone, walk-in)
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_person', 120)->nullable()->after('school');
            $table->string('district', 80)->nullable()->after('contact_person');
            $table->text('admin_note')->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'district', 'admin_note']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['admin_note', 'courier_note', 'paid_at', 'stock_deducted_at', 'source']);
        });
    }
};
