<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductStockTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('Product_Stock', function (Blueprint $table) {
            $table->unsignedMediumInteger('_celler');
            $table->unsignedInteger('_product');
            $table->float('min', 8,2);
            $table->float('max', 8,2);
            $table->float('stock', 8,2);
            $table->foreign('_celler')->references('id')->on('celler'); 
            $table->foreign('_product')->references('id')->on('products'); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('Product_Stock');
    }
}
