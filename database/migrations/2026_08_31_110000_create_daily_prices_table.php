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
        Schema::create('daily_prices', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('item_variety_id')->constrained('item_varieties')->onDelete('restrict');
            $table->decimal('buying_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->unique(['date', 'item_variety_id'], 'unique_daily_variety_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_prices');
    }
};
