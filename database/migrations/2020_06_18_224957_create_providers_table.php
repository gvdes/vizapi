<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProvidersTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('providers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name','50')->unique();
            $table->string('alias','25');
            $table->json('adress');
            $table->string('phone','15');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('providers');
    }
}
