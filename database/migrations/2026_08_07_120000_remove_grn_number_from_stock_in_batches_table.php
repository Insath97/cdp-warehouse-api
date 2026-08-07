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
        Schema::table('stock_in_batches', function (Blueprint $table) {
            $table->dropColumn('grn_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_in_batches', function (Blueprint $table) {
            $table->string('grn_number', 100)->nullable();
        });
    }
};
