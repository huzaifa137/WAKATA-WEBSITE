<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_threads', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 20)->nullable()->unique();                   // T-1001, filled right after the insert
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic', 40);
            $table->string('subject', 160);
            $table->string('status', 12)->default('open');                     // open (we owe a reply) | answered | closed
            $table->timestamp('customer_read_at')->nullable();                 // when the customer last opened the thread
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'last_message_at']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_thread_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 10);                                      // customer | staff
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['support_thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_threads');
    }
};
