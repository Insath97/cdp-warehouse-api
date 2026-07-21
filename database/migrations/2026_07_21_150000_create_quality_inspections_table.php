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
        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_in_batch_id')->nullable()->constrained('stock_in_batches')->cascadeOnDelete();
            $table->foreignId('stock_bag_id')->nullable()->constrained('stock_bags')->cascadeOnDelete();
            $table->foreignId('item_type_id')->nullable()->constrained('item_types')->cascadeOnDelete();
            $table->foreignId('item_variety_id')->constrained('item_varieties')->cascadeOnDelete();

            $table->decimal('original_weight', 10, 2)->nullable();
            $table->decimal('current_weight', 10, 2)->nullable();
            $table->decimal('weight_difference', 10, 2)->nullable();
            $table->enum('weight_change_type', ['no_change', 'weight_loss', 'weight_gain'])->default('no_change');

            $table->decimal('moisture_percentage', 5, 2)->nullable();
            $table->enum('grade', ['A', 'B', 'C', 'reject'])->default('A');
            $table->decimal('broken_percentage', 5, 2)->nullable();
            $table->enum('colour_quality', ['good', 'acceptable', 'poor'])->nullable();
            $table->enum('inspection_result', ['approved', 'conditional', 'rejected'])->default('approved');
            $table->text('remarks')->nullable();

            $table->foreignId('inspected_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('inspected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_inspections');
    }
};
