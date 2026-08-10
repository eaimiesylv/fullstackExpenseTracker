<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_splits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignUlid('group_member_id')->constrained('group_members')->cascadeOnDelete();
            $table->string('split_type')->default('equal')->index();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['expense_id', 'group_member_id']);
        });

        DB::statement('ALTER TABLE expense_splits ADD CONSTRAINT expense_splits_amount_non_negative CHECK (amount IS NULL OR amount >= 0)');
        DB::statement('ALTER TABLE expense_splits ADD CONSTRAINT expense_splits_percentage_range CHECK (percentage IS NULL OR (percentage >= 0 AND percentage <= 100))');
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_splits');
    }
};