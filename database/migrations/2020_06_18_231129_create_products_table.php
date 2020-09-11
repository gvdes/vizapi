<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code',25)->unique();
            $table->string('name', 50);
            $table->string('description', 200);
            $table->float('stock', 8, 2)->nullable();
            $table->smallInteger('pieces');
            $table->json('dimensions')->nullable();
            $table->float('weight', 6, 2)->nullable();
            $table->unsignedSmallInteger('_category');
            $table->unsignedTinyInteger('_status');
            $table->unsignedTinyInteger('_unit');
            $table->unsignedInteger('_provider');
            $table->timestamps();
            $table->foreign('_category')->references('id')->on('product_categories');
            $table->foreign('_status')->references('id')->on('product_status');
            $table->foreign('_unit')->references('id')->on('product_units');
            $table->foreign('_provider')->references('id')->on('providers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('products');
    }
}
