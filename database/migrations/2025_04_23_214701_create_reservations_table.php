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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignId('post_id')->constrained()->onDelete('cascade'); // Foreign key to 'posts' table
            $table->foreignId('service_id')->constrained()->onDelete('cascade'); // Foreign key to 'services' table
            $table->timestamp('started_at'); // Reservation start time
            $table->timestamp('ended_at'); // Reservation end time
            $table->decimal('total_price', 10, 2); // Total price for the reservation
            $table->timestamps(); // Created at & Updated at timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
