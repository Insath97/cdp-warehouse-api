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
        Schema::create('stock_bags', function (Blueprint $table) {
            $table->id();
            $table->string('bag_code', 50)->unique();
            $table->integer('bag_number');
            $table->foreignId('stock_in_batch_id')->constrained('stock_in_batches')->onDelete('cascade');
            $table->foreignId('stock_in_batch_item_id')->nullable()->constrained('stock_in_batch_items')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('item_type_id')->constrained('item_types')->onDelete('cascade');
            $table->foreignId('item_variety_id')->constrained('item_varieties')->onDelete('cascade');
            
            $table->decimal('bag_weight', 10, 2);
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->decimal('selling_price', 10, 2)->default(0.00);
            $table->decimal('total_price', 15, 2)->default(0.00);
            $table->decimal('total_sales_amount', 15, 2)->default(0.00);

            $table->enum('status', ['in_stock', 'dispatched', 'damaged', 'returned'])->default('in_stock');
            $table->string('barcode_code', 100)->unique();
            $table->string('qr_code', 255)->unique();
            $table->string('location_id', 100)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Reporting Indexes
            $table->index(['status', 'warehouse_id', 'item_type_id', 'item_variety_id'], 'idx_stock_bags_report');
            $table->index('created_at');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_bags');
    }
};
