<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignUlid('group_member_id')->nullable()->constrained('group_members')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contributor_name')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->date('contribution_date')->index();
            $table->string('payment_reference')->nullable()->index();
            $table->string('payment_method')->nullable()->default('bank_transfer');
            $table->text('notes')->nullable();
            $table->string('status')->default('completed')->index();
            $table->timestamps();
            $table->index(['budget_id', 'group_member_id']);
            $table->index(['budget_id', 'user_id']);
        });

        DB::statement('ALTER TABLE contributions ADD CONSTRAINT contributions_amount_positive CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};