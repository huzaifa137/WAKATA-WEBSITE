<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Shown on invoices (School or organisation tab)
            $table->string('invoice_name', 160)->nullable()->after('school');
            $table->string('org_address', 255)->nullable()->after('invoice_name');
            $table->string('accounts_email', 190)->nullable()->after('org_address');

            // Optional two-step code at sign-in, emailed and valid for 10 minutes (same idea as the admin console)
            $table->boolean('two_step_enabled')->default(false)->after('status');
            $table->string('otp_code')->nullable()->after('two_step_enabled');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');

            // Raised by "sign out everywhere else" and by password changes: sessions started before it stop working.
            $table->unsignedInteger('session_epoch')->default(0)->after('otp_attempts');

            // Small settings that are not worth a column each: notifications, saved mobile money numbers,
            // delivery preference, stamp area on invoices.
            $table->json('preferences')->nullable()->after('session_epoch');
        });

        // Module 3 kept demo "sample portal" data behind this flag. The portal now reads real orders.
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('preview_data');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('preview_data')->default(false);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_name', 'org_address', 'accounts_email', 'two_step_enabled', 'otp_code', 'otp_expires_at',
                'otp_attempts', 'session_epoch', 'preferences',
            ]);
        });
    }
};
