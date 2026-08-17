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
            $table->foreignUlid('group_member_id')->nullable()->constrained('group_members')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('participant_name')->nullable();
            $table->boolean('is_guest')->default(false);
            $table->decimal('amount_assigned', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status')->default('no_payment')->index(); // no_payment, incomplete, full
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['bill_id', 'group_member_id']);
            $table->index(['bill_id', 'user_id']);
        });

        DB::statement('ALTER TABLE bill_participants ADD CONSTRAINT bill_participants_assigned_positive CHECK (amount_assigned >= 0)');
        DB::statement('ALTER TABLE bill_participants ADD CONSTRAINT bill_participants_paid_positive CHECK (amount_paid >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_participants');
    }
};