<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductAttributesTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->string('value',100);
            $table->unsignedInteger('_attribute');
            $table->unsignedInteger('_product');
            $table->foreign('_attribute')->references('id')->on('category_attributes');
            $table->foreign('_product')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('product_attributes');
    }
}
