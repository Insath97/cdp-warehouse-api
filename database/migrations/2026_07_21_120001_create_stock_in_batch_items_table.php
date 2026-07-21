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
        Schema::create('stock_in_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_in_batch_id')->constrained('stock_in_batches')->onDelete('cascade');
            $table->foreignId('item_type_id')->constrained('item_types')->onDelete('cascade');
            $table->foreignId('item_variety_id')->constrained('item_varieties')->onDelete('cascade');
            $table->integer('quantity_bags')->default(0);
            $table->decimal('unit_weight', 10, 2)->default(0.00);
            $table->decimal('total_weight', 10, 2)->default(0.00);
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->decimal('total_price', 15, 2)->default(0.00);
            $table->integer('remaining_quantity_bags')->default(0);
            $table->decimal('remaining_weight', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_in_batch_items');
    }
};
