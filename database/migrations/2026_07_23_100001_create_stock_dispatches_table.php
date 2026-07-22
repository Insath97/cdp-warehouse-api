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
        Schema::create('stock_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_number', 50)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('buyers')->onDelete('cascade');
            $table->enum('dispatch_type', ['sales', 'customer_delivery', 'processing', 'transfer']);
            $table->date('dispatch_date');
            $table->string('delivery_note_reference', 100)->nullable();
            
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('vehicle_log_id')->nullable()->constrained('vehicle_logs')->nullOnDelete();
            
            $table->integer('total_bags')->default(0);
            $table->decimal('total_weight', 10, 2)->default(0.00);
            $table->decimal('total_sales_amount', 15, 2)->default(0.00);
            
            $table->enum('status', ['draft', 'pending_gate_pass', 'dispatched', 'cancelled'])->default('draft');
            $table->string('gate_pass_number', 50)->nullable()->unique();
            $table->dateTime('gate_exit_at')->nullable();
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
        Schema::dropIfExists('stock_dispatches');
    }
};
