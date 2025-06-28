<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductWarehouseTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('product_warehouse', function(Blueprint $table)
		{
			$table->id();
			$table->unsignedBigInteger('product_id')->index('product_warehouse_id');
			$table->unsignedBigInteger('warehouse_id')->index('warehouse_id');
			$table->unsignedBigInteger('product_variant_id')->nullable()->index('product_variant_id');
			$table->float('qte', 10, 0);
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
		Schema::drop('product_warehouse');
	}

}
