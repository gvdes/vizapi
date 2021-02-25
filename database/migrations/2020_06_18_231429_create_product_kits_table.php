<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductKitsTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('product_kits', function (Blueprint $table) {
            $table->unsignedMediumInteger('_kit');
            $table->unsignedInteger('_product');
            $table->float('price', 8, 2);
            $table->foreign('_kit')->references('id')->on('kits');
            $table->foreign('_product')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('product_kits');
    }
}
