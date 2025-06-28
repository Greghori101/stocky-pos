<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransferMoneyTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('transfer_money', function(Blueprint $table)
		{
			$table->id();
			$table->unsignedBigInteger('from_account_id')->index('from_account_id');
			$table->unsignedBigInteger('to_account_id')->index('to_account_id');
			$table->date('date');
			$table->float('amount', 10, 0);
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
		Schema::drop('transfer_money');
	}

}
