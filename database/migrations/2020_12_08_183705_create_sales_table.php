<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('sales', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('num_ticket');
            $table->string('name', 150);
            $table->timestamps();
            
            $table->unsignedSmallInteger("_workpoint");
            $table->foreign('_workpoint')->references('id')->on('workpoints');
            $table->unsignedSmallInteger("_cash");
            $table->foreign('_cash')->references('id')->on('cash_registers');
            $table->unsignedInteger("_client");
            $table->foreign('_client')->references('id')->on('client');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('sales');
    }
}
