<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignUlid('bill_category_id')->nullable()->constrained('bill_categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('frequency')->default('none')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->index(['owner_id', 'group_id', 'bill_category_id']);
        });

        DB::statement('ALTER TABLE bills ADD CONSTRAINT bills_amount_positive CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};