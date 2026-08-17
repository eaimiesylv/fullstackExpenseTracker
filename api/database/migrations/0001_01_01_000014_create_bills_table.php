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
            $table->foreignUlid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('scope')->default('personal')->index();
            $table->string('split_type')->default('equal')->index(); // equal, fixed, custom
            $table->date('start_date')->nullable()->index();
            $table->date('due_date')->nullable()->index();
            $table->boolean('allow_partial_payment')->default(true);
            $table->string('reminder_type')->default('none'); // none, now, custom
            $table->string('reminder_frequency')->nullable();
            $table->string('status')->default('no_payment')->index(); // no_payment, incomplete, full
            $table->timestamps();
            $table->index(['owner_id', 'group_id', 'category_id']);
            $table->index(['start_date', 'due_date']);
        });

        DB::statement('ALTER TABLE bills ADD CONSTRAINT bills_amount_positive CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};