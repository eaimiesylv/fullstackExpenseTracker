<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->foreignUlid('group_member_id')->constrained('group_members')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['bill_id', 'group_member_id']);
        });

        DB::statement('ALTER TABLE bill_participants ADD CONSTRAINT bill_participants_amount_positive CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_participants');
    }
};