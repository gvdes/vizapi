<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('client', function (Blueprint $table) {
            $table->increments('id');
            $table->string("name", 150);
            $table->string("phone", 15);
            $table->string("email", 15);
            $table->string("rfc", 15);
            $table->json("address");
            $table->timestamps();
            
            $table->unsignedTinyInteger("_price_list");
            $table->foreign('_price_list')->references('id')->on('price_list');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client');
    }
}
