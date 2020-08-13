<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModulesAppTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('modules_app', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 45)->unique();
            $table->tinyInteger('deep');
            $table->tinyInteger('root');
            $table->string('path', 60);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('modules_app');
    }
}
