<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCellerSectionTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('celler_section', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->string('name', 25);
            $table->string('alias', 20);
            $table->string('path', 40);
            $table->tinyInteger('root');
            $table->tinyInteger('deep');
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
        Schema::dropIfExists('celler_section');
    }
}
