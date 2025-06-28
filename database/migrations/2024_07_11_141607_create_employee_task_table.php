<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeTaskTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('employee_task', function(Blueprint $table)
		{
			
			$table->unsignedBigInteger('employee_id')->index('employee_task_employee_id');
			$table->unsignedBigInteger('task_id')->index('employee_task_task_id');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('employee_task');
	}

}
