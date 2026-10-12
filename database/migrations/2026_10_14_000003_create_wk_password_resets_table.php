<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One table for both areas: the same email can exist as an admin and as a customer.
        Schema::create('wk_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('guard', 20);          // admin | customer
            $table->string('email');
            $table->string('token');              // sha256 of the emailed token
            $table->timestamp('created_at')->nullable();
            $table->unique(['guard', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wk_password_resets');
    }
};
