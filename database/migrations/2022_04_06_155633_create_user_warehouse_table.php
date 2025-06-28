<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Warehouse;
use App\Models\User;

class CreateUserWarehouseTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('user_warehouse', function(Blueprint $table)
		{
			
			$table->unsignedBigInteger('user_id')->index('user_warehouse_user_id');
			$table->unsignedBigInteger('warehouse_id')->index('user_warehouse_warehouse_id');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('user_warehouse');
	}

}
