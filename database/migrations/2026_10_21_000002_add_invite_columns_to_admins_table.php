<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Module 10: a team member who was invited but has not chosen a password yet has status "invited". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('last_login_ip');
            $table->unsignedBigInteger('invited_by')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['invited_at', 'invited_by']);
        });
    }
};
