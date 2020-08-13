<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductOrderedTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('product_ordered', function (Blueprint $table) {
            $table->string('kit', '35');
            $table->float('units', 8, 2);
            /* $table->float('containers', 8, 2); */
            $table->float('price', 8, 2);
            $table->unsignedInteger('_product');
            $table->unsignedInteger('_order');
            $table->unsignedInteger('_supply_by');
            $table->unsignedTinyInteger('_price_list');
            $table->foreign('_product')->references('id')->on('products');
            $table->foreign('_order')->references('id')->on('orders');
            $table->foreign('_supply_by')->references('id')->on('account_workpoints');
            $table->foreign('_price_list')->references('id')->on('price_list');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('product_ordered');
    }
}
