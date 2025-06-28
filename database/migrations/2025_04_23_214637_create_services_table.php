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
        Schema::create('services', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->decimal('price', 10, 2); // Price column (for the service)
            $table->unsignedBigInteger('unit_per_minute')->default(1); // Price per minute for the service
            $table->timestamp('deleted_at')->nullable(); // Created at & Updated at timestamps
            $table->timestamps(); // Created at & Updated at timestamps
			$table->text('image')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
