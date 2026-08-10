<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignUlid('group_member_id')->constrained('group_members')->cascadeOnDelete();
            $table->string('role')->default('viewer');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_submit')->default(false);
            $table->boolean('can_spend')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->timestamps();
            $table->unique(['budget_id', 'group_member_id']);
            $table->index('budget_id');
            $table->index('group_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_members');
    }
};