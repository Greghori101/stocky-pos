<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReservationDraftsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('reservation_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('ref')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('status')->nullable();
            $table->double('total_price', 15, 2)->default(0);
            $table->double('discount', 15, 2)->default(0);
            $table->double('paid_amount', 15, 2)->default(0);
            $table->string('payment_status')->nullable();
            $table->date('date')->nullable();
            $table->double('tax_net', 15, 2)->default(0);
            $table->double('tax_rate', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->integer('qte_return')->default(0);
            $table->double('total_return', 15, 2)->default(0);

            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('post_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('set null');
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('reservation_drafts');
    }
}
