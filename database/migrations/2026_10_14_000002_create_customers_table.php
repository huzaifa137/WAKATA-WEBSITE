<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('type', 20)->default('Teacher');     // Teacher, School, Parent, Bookshop
            $table->string('school')->nullable();
            $table->string('password');
            $table->string('status', 20)->default('active');    // active, suspended
            // Until module 6 the customer portal still shows the prototype's sample orders.
            // true = show those samples (seeded demo customer); false = show empty states (real sign-ups).
            $table->boolean('preview_data')->default(false);
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
