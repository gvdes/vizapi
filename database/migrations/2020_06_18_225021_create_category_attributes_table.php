<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryAttributesTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 60);
            $table->unsignedSmallInteger('_category');
            $table->boolean('required');
            $table->foreign('_category')->references('id')->on('product_categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('category_attributes');
    }
}
