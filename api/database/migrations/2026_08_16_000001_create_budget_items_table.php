<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->foreignUlid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
