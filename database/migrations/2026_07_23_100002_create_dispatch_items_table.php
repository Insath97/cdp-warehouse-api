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
        Schema::create('dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_dispatch_id')->constrained('stock_dispatches')->onDelete('cascade');
            $table->foreignId('stock_bag_id')->constrained('stock_bags')->onDelete('restrict');
            $table->decimal('selling_price', 10, 2)->default(0.00);
            $table->decimal('bag_weight', 10, 2);
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
        Schema::dropIfExists('dispatch_items');
    }
};
