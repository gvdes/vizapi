<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkpointsTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('workpoints', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 45)->unique();
            $table->string('alias', 10);
            $table->unsignedTinyInteger('_type');

            $table->foreign('_type')->references('id')->on('workpoints_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('workpoints');
    }
}
