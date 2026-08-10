<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignUlid('group_member_id')->nullable()->constrained('group_members')->nullOnDelete();
            $table->foreignUlid('budget_id')->nullable()->constrained('budgets')->nullOnDelete();
            $table->foreignUlid('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('expense_type')->default('personal')->index();
            $table->date('expense_date')->index();
            $table->string('status')->default('pending')->index();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['budget_id', 'user_id', 'group_member_id', 'expense_category_id'], 'expenses_budget_user_group_exp_cat_idx');
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_positive CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};