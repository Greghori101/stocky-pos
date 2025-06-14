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
        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade'); // Foreign key to 'reservations' table
            $table->integer('product_id'); // Foreign key to 'products' table
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->decimal('price', 10, 2); // Price of the product in the reservation
            $table->unsignedBigInteger('qte')->default(0); // Quantity of the product in the reservation
            $table->timestamps(); // Created at & Updated at timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_items');
    }
};
