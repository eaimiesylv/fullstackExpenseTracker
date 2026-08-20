<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('needs', function (Blueprint $table) {
            $table->string('visibility_type')->default('all_members')->after('type');
            $table->json('visible_user_ids')->nullable()->after('visibility_type');
        });
    }

    public function down(): void
    {
        Schema::table('needs', function (Blueprint $table) {
            $table->dropColumn(['visibility_type', 'visible_user_ids']);
        });
    }
};
