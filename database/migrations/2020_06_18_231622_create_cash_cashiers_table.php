<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashCashiersTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('cash_cashiers', function (Blueprint $table) {
            $table->unsignedInteger('_cashier');
            $table->unsignedSmallInteger('_cash_register');
            $table->unsignedSmallInteger('_workpoint');
            $table->unsignedSmallInteger('_printer');
            $table->foreign('_cashier')->references('_account')->on('account_workpoints');
            $table->foreign('_cash_register')->references('id')->on('cash_registers');
            $table->foreign('_workpoint')->references('id')->on('workpoints');
            $table->foreign('_printer')->references('id')->on('printers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('cash_cashiers');
    }
}
