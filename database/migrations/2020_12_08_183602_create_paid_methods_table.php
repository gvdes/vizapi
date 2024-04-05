<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaidMethodsTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('paid_methods', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string("name", 150);
            $table->string("alias", 15);
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
