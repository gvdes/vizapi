<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashRegisterTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 25);
            $table->unsignedTinyInteger('_status');
            $table->unsignedSmallInteger('_workpoint');
            $table->foreign('_status')->references('id')->on('cash_status');
            $table->foreign('_workpoint')->references('id')->on('workpoints');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('cash_register');
    }
}
