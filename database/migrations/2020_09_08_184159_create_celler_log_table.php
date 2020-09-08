<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCellerLogTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('celler_log', function (Blueprint $table) {
            $table->increments('id');
            $table->json('details');
            $table->unsignedMediumInteger('_celler');
            $table->foreign('_celler')->references('id')->on('celler');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('celler_log');
    }
}
