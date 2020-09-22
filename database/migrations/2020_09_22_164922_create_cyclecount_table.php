<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCyclecountTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('cyclecount', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('_workpoint');
            $table->unsignedInteger('_created_by');
            $table->unsignedTinyInteger('_type');
            $table->tinyInteger('status');
            $table->json('details');
            $table->timestamps();

            $table->foreign('_workpoint')->references('id')->on('workpoints');
            $table->foreign('_created_by')->references('id')->on('accounts');
            $table->foreign('_type')->references('id')->on('cyclecount_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('cyclecount');
    }
}
