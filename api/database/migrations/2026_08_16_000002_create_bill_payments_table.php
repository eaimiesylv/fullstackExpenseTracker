<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->foreignUlid('bill_participant_id')->nullable()->constrained('bill_participants')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payer_name')->nullable();
            $table->boolean('is_guest')->default(false);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->index();
            $table->string('payment_method')->nullable()->default('bank_transfer');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE bill_payments ADD CONSTRAINT bill_payments_amount_positive CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};
