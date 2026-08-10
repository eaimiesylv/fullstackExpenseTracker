<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignUlid('group_member_id')->constrained('group_members')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('request_type')->default('planned_expense')->index();
            $table->string('status')->default('pending')->index();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['budget_id', 'group_member_id']);
            $table->index(['approved_by', 'approved_at']);
        });

        DB::statement('ALTER TABLE budget_requests ADD CONSTRAINT budget_requests_amount_positive CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_requests');
    }
};