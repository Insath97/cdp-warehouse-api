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
        Schema::create('item_varieties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_type_id')->constrained('item_types')->onDelete('cascade');
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Enforce unique variety name and slug within the same item category
            $table->unique(['item_type_id', 'name'], 'item_varieties_name_unique');
            $table->unique(['item_type_id', 'slug'], 'item_varieties_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_varieties');
    }
};
