<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();           // WK-10001, filled right after the insert
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); // null = guest checkout

            // Who ordered (copied at checkout, so it stays correct if the account changes later)
            $table->string('customer_name', 120);
            $table->string('customer_email', 190);
            $table->string('customer_phone', 30);

            $table->string('delivery_method', 20);                        // pickup | kampala | upcountry | email
            $table->text('delivery_address')->nullable();
            $table->string('payment_method', 20);                         // mtn | airtel | card | cash
            $table->string('payment_status', 20)->default('pending');     // pending | paid | failed | refunded
            $table->string('status', 20)->default('pending');             // pending | confirmed | processing | ready | shipped | completed | cancelled | refunded
            $table->string('transaction_ref', 60)->nullable();

            $table->unsignedInteger('subtotal');                          // UGX
            $table->unsignedInteger('delivery_fee')->default(0);          // UGX
            $table->unsignedInteger('total');                             // UGX
            $table->text('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
