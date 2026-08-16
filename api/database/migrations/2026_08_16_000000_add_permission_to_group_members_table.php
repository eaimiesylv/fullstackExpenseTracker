<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_members') && ! Schema::hasColumn('group_members', 'permission')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->string('permission')->default('viewer')->after('role')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_members') && Schema::hasColumn('group_members', 'permission')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->dropColumn('permission');
            });
        }
    }
};
