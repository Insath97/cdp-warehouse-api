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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('purchase_price_per_kg', 10, 2)->nullable()->change();
            $table->integer('number_of_bags')->nullable()->change();
            $table->decimal('total_weights', 10, 2)->nullable()->change();
            $table->decimal('total_sales_price', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('purchase_price_per_kg', 10, 2)->nullable(false)->change();
            $table->integer('number_of_bags')->nullable(false)->change();
            $table->decimal('total_weights', 10, 2)->nullable(false)->change();
            $table->decimal('total_sales_price', 15, 2)->nullable(false)->change();
        });
    }
};
