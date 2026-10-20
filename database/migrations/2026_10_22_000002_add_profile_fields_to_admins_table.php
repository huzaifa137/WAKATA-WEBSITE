<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Module 11: a team member's profile photo and their own email alert choices. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('phone');
            $table->json('prefs')->nullable()->after('avatar_path');      // {"alert_orders": true, "alert_messages": false}
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'prefs']);
        });
    }
};
