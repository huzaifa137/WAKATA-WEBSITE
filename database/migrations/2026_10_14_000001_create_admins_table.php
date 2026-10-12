<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('password');
            $table->string('role', 30)->default('editor');      // super_admin, manager, editor, support
            $table->string('status', 20)->default('active');    // active, suspended
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('otp_code')->nullable();             // hashed 6-digit sign-in code
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->string('remember_token', 100)->nullable();  // sha256 of the "keep me signed in" cookie token
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
