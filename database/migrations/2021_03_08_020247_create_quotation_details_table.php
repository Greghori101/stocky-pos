<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuotationDetailsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('quotation_details', function(Blueprint $table)
		{
			$table->id();
			$table->float('price', 10, 0);
			$table->float('TaxNet', 10, 0)->nullable()->default(0);
			$table->string('tax_method', 192)->nullable()->default('1');
			$table->float('discount', 10, 0)->nullable()->default(0);
			$table->string('discount_method', 192)->nullable()->default('1');
			$table->float('total', 10, 0);
			$table->float('quantity', 10, 0);
			$table->unsignedBigInteger('product_id')->index('product_id_quotation_details');
			$table->unsignedBigInteger('product_variant_id')->nullable()->index('quote_product_variant_id');
			$table->unsignedBigInteger('quotation_id')->index('quotation_id');
			$table->timestamps(6);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('quotation_details');
	}

}
