<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequisitionLogTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('requisition_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('_order');
            $table->unsignedSmallInteger('_status');
            $table->json('details');
            $table->timestamps();

            $table->foreign('_order')->references('id')->on('requisition');
            $table->foreign('_status')->references('id')->on('requisition_process');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('requisition_log');
    }
}
