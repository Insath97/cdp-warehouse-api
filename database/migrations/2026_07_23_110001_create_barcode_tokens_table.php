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
        Schema::create('barcode_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barcode_token_batch_id')->constrained('barcode_token_batches')->onDelete('cascade');
            $table->string('token_code', 13)->unique()->index();
            $table->enum('status', ['unused', 'used', 'cancelled'])->default('unused');
            
            $table->dateTime('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcode_tokens');
    }
};
