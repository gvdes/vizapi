<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrintersGrocesTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('printers_grocer', function (Blueprint $table) {
            $table->unsignedSmallInteger('_printer');
            $table->unsignedInteger('_grocer');
            $table->boolean('active');
            $table->foreign('_printer')->references('id')->on('printers');
            $table->foreign('_grocer')->references('_account')->on('account_workpoints');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('printers_groces');
    }
}
