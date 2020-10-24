<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderProcessTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('order_process', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 35)->unique();
            $table->boolean('active');
            $table->boolean('allow');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('order_process');
    }
}
