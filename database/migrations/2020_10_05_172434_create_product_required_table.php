<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductRequiredTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('product_required', function (Blueprint $table) {
            $table->unsignedInteger('_product');
            $table->unsignedInteger('_requisition');
            $table->float('units', 8, 2);
            $table->string('comments', 100);

            $table->foreign('_product')->references('id')->on('products');
            $table->foreign('_requisition')->references('id')->on('requisition');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('product_required');
    }
}
