<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->foreignUlid('budget_category_id')->nullable()->constrained('budget_categories')->nullOnDelete();
            $table->string('budget_name');
            $table->text('description')->nullable();
            $table->string('scope')->default('personal')->index();
            $table->string('purpose_type')->default('standard')->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('NGN');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_frequency')->nullable();
            $table->boolean('allow_member_submission')->default(false);
            $table->boolean('require_approval')->default(false);
            $table->boolean('track_contributions')->default(false);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->index(['owner_id', 'group_id']);
            $table->index(['start_date', 'end_date']);
        });

        DB::statement('ALTER TABLE budgets ADD CONSTRAINT budgets_amount_positive CHECK (amount >= 0)');
        DB::statement('ALTER TABLE budgets ADD CONSTRAINT budgets_dates_valid CHECK (end_date IS NULL OR end_date >= start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};