<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCyclecountBodyTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('cyclecount_body', function (Blueprint $table) {
            $table->unsignedInteger('_cyclecount');
            $table->unsignedInteger('_product');
            $table->float('stock', 8, 2);
            $table->float('stock_acc', 8, 2);
            $table->json('details');

            $table->foreign('_product')->references('id')->on('products');
            $table->foreign('_cyclecount')->references('id')->on('cyclecount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('cyclecount_body');
    }
}
