<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentReservationReturnsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('payment_reservation_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservation_return_id');
            $table->date('date');
            $table->double('amount', 15, 2);
            $table->double('change', 15, 2)->default(0);
            $table->string('ref')->nullable();
            $table->double('discount', 15, 2)->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('reservation_return_id')->references('id')->on('reservation_returns')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('payment_reservation_returns');
    }
}
