<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('needs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('personal');
            $table->decimal('amount', 15, 2);
            $table->foreignUlid('category_id')->constrained('categories')->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignUlid('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->boolean('allow_member_contribution')->default(false);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('needs');
    }
};
