<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductLocationTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('product_location', function (Blueprint $table) {
            $table->unsignedMediumInteger('_location');
            $table->unsignedInteger('_product');
            $table->foreign('_location')->references('id')->on('celler_section');
            $table->foreign('_product')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('product_location');
    }
}
