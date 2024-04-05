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
            $table->float('total', 12, 2);
            $table->timestamps();
            
            $table->unsignedSmallInteger("_cash");
            $table->foreign('_cash')->references('id')->on('cash_registers');
            $table->unsignedInteger("_client");
            $table->foreign('_client')->references('id')->on('client');
            $table->unsignedSmallInteger("_paid_by");
            $table->foreign('_paid_by')->references('id')->on('paid_methods');
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
