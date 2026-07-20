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
        Schema::create('vehicle_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_number', 50)->unique();
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->enum('log_type', ['stock_in', 'stock_out']);
            $table->enum('direction', ['in', 'out'])->default('in');
            $table->dateTime('entry_time');
            $table->dateTime('exit_time')->nullable();
            $table->string('driver_name', 255);
            $table->string('driver_phone', 20)->nullable();
            $table->string('driver_nic', 20)->nullable();
            $table->string('purpose', 255)->nullable();
            $table->text('notes')->nullable();

            // Entry image & document uploads
            $table->string('entry_license_plate_image', 500)->nullable();
            $table->string('entry_vehicle_image', 500)->nullable();
            $table->string('entry_document', 500)->nullable();

            // Exit image & document uploads
            $table->string('exit_license_plate_image', 500)->nullable();
            $table->string('exit_vehicle_image', 500)->nullable();
            $table->string('exit_document', 500)->nullable();

            $table->foreignId('logged_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_logs');
    }
};
