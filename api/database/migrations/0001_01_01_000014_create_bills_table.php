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
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('bill_category_id')->nullable()->constrained('bill_categories')->nullOnDelete();
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