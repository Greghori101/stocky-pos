<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReservationItemsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('product_id');
            $table->double('price', 15, 2)->default(0);
            $table->integer('qte')->default(0);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->double('total', 15, 2)->default(0);
            $table->double('tax_net', 15, 2)->default(0);
            $table->string('tax_method')->nullable();
            $table->double('discount', 15, 2)->default(0);
            $table->string('discount_method')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('reservation_items');
    }
}
