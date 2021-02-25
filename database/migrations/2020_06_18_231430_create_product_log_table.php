<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductLogTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('product_log', function (Blueprint $table) {
            /* $table->unsignedInteger('_account'); */
            $table->unsignedTinyInteger('_action');
            $table->unsignedInteger('_product');
            $table->json('details');
            $table->timestamps();
            $table->foreign('_action')->references('id')->on('product_actions');
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
