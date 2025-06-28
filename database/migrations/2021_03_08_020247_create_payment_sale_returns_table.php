<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentSaleReturnsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('payment_sale_returns', function(Blueprint $table)
		{
			$table->id();
			$table->unsignedBigInteger('user_id')->index('factures_sale_return_user_id');
			$table->date('date');
			$table->string('Ref', 192);
			$table->unsignedBigInteger('sale_return_id')->index('factures_sale_return');
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
		Schema::drop('payment_sale_returns');
	}

}
