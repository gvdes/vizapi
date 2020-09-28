<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductVariantsTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('product_variants', function (Blueprint $table) {
            $table->increments('id');
            $table->string('barcode', 30)->unique();
            $table->float('stock', 8, 2)->default(0);
            $table->unsignedInteger('_product');
            $table->foreign('_product')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('product_variants');
    }
}
