<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductSoldTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('product_sold', function (Blueprint $table) {
            $table->float("amount", 10,2);
            $table->float("costo", 10,2);
            $table->float("price", 10,2);
            $table->float("total", 12,2);

            $table->unsignedInteger("_product");
            $table->foreign('_product')->references('id')->on('products');
            $table->unsignedInteger("_sale");
            $table->foreign('_sale')->references('id')->on('sales');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('product_sold');
    }
}
