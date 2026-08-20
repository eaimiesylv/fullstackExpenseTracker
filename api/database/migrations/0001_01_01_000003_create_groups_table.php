<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('group_name', 25);
            $table->text('description')->nullable();
           // $table->string('image', 255)->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->unique(['owner_id', 'group_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};