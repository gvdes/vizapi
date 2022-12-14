<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountWorkpointTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('account_workpoints', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('_account');
            $table->unsignedSmallInteger('_workpoint');
            $table->unsignedTinyInteger('_status');
            $table->unsignedTinyInteger('_rol');

            $table->foreign('_account')->references('id')->on('accounts');
            $table->foreign('_workpoint')->references('id')->on('workpoints');
            $table->foreign('_status')->references('id')->on('account_status');
            $table->foreign('_rol')->references('id')->on('roles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_workpoint');
    }
}
