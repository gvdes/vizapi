<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCatalogReportTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('catalog_report', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 30)->unique();
            $table->string('description', 50);
            $table->json('data');
            $table->unsignedSmallInteger('_work_team');
            $table->unsignedInteger('_resposable');
            $table->foreign('_work_team')->references('id')->on('work_team');
            $table->foreign('_resposable')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('catalog_report');
    }
}
