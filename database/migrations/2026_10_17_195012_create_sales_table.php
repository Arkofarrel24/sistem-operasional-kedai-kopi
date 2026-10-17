<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('total', 12, 2);
            $table->enum('payment_method', ['cash', 'qris', 'transfer']);
            $table->decimal('paid_amount', 12, 2);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->enum('status', ['completed', 'void'])->default('completed');
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
