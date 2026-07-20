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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('phone_primary', 20);
            $table->string('phone_secondary', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100);
            $table->foreignId('country_id')->constrained('countries')->onDelete('restrict');
            $table->foreignId('district_id')->nullable()->constrained('districts')->onDelete('restrict');
            $table->enum('id_type', ['nic', 'driving', 'passport', 'other'])->nullable();
            $table->string('id_number', 50)->nullable();
            $table->enum('payment_terms', ['immediate', 'net_7', 'net_15', 'net_30'])->default('immediate');
            $table->decimal('outstanding_balance', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique index for the combination of id_type and id_number when they are not null
            $table->unique(['id_type', 'id_number'], 'suppliers_id_type_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
