<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCellerTypeTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('celler_type', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 25)->unique();
            $table->string('alias', 5)->unique();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('celler_type');
    }
}
