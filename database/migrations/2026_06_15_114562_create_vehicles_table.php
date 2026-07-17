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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_number', 20)->unique();
            $table->string('driver_name', 255)->nullable();
            $table->string('driver_phone', 20)->nullable();
            $table->string('driver_nic', 20)->nullable();
            $table->enum('vehicle_type', ['lorry', 'pickup', 'van', 'tractor', 'other']);
            $table->decimal('tare_weight', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
