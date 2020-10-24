<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderLogTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('order_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('_order');
            $table->unsignedSmallInteger('_status');
            $table->json('details');
            $table->timestamps();
            $table->foreign('_order')->references('id')->on('orders');
            $table->foreign('_status')->references('id')->on('order_process');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('order_log');
    }
}
