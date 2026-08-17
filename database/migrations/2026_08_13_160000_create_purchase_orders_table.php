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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('item_variety_id')->constrained('item_varieties')->onDelete('restrict');
            $table->enum('variety_type', ['wet', 'dry', 'midwet']);
            $table->decimal('purchase_price_per_kg', 10, 2);
            $table->decimal('market_price_per_kg', 10, 2)->nullable();
            $table->integer('number_of_bags');
            $table->decimal('total_weights', 10, 2);
            $table->decimal('total_sales_price', 15, 2);
            $table->decimal('total_market_price', 15, 2)->nullable();
            $table->enum('status', ['pending_approval', 'price_suggested', 'approved', 'verified', 'rejected', 'cancelled'])->default('pending_approval');
            $table->enum('payment_status', ['pending', 'paid', 'cancel'])->default('pending');
            $table->string('payment_proof_document')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
