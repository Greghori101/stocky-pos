<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentPurchaseReturnsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('payment_purchase_returns', function(Blueprint $table)
		{
			$table->id();
			$table->unsignedBigInteger('user_id')->index('user_id_payment_return_purchase');
			$table->date('date');
			$table->string('Ref', 192);
			$table->unsignedBigInteger('purchase_return_id')->index('supplier_id_payment_return_purchase');
			$table->float('montant', 10, 0);
			$table->string('Reglement', 191);
			$table->text('notes')->nullable();
			$table->timestamps(6);
			$table->softDeletes();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('payment_purchase_returns');
	}

}
