<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrintersTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('printers', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 25);
            $table->string('ip', 15);
            $table->unsignedTinyInteger('_type');
            $table->unsignedSmallInteger('_workpoint');
            $table->foreign('_type')->references('id')->on('printer_types');
            $table->foreign('_workpoint')->references('id')->on('workpoints');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('printers');
    }
}
