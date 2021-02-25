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
            $table->string('rfc','20')->nullable();
            $table->string('name','100')->unique();
            $table->string('alias','50');
            $table->string('description','200');
            $table->json('adress')->nullable();
            $table->string('phone','15')->nullable();
            $table->string('email','100')->nullable();
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
