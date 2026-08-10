<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUlid('group_member_id')->nullable()->constrained('group_members')->nullOnDelete();
            $table->foreignUlid('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('phone_number')->nullable()->index();
            $table->string('token')->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['group_id', 'group_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};