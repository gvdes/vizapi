<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCellerTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('celler', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->string('name', 25);
            $table->unsignedSmallInteger('_workpoint');
            $table->unsignedSmallInteger('_type');
            $table->foreign('_workpoint')->references('id')->on('workpoints');
            $table->foreign('_type')->references('id')->on('celler_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('celler');
    }
}
