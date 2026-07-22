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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('buyer_id')->constrained('buyers')->onDelete('cascade');
            $table->foreignId('stock_dispatch_id')->nullable()->constrained('stock_dispatches')->onDelete('cascade');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            
            $table->decimal('sub_total', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid', 'void'])->default('unpaid');
            $table->string('payment_method', 50)->nullable();
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
