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
        Schema::create('barcode_token_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->unique();
            $table->foreignId('item_type_id')->nullable()->constrained('item_types')->nullOnDelete();
            $table->foreignId('item_variety_id')->nullable()->constrained('item_varieties')->nullOnDelete();
            $table->enum('token_type', ['qr', 'barcode']);
            $table->integer('quantity_requested');
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcode_token_batches');
    }
};
