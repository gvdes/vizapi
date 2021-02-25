<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequisitionTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('requisition', function (Blueprint $table) {
            $table->increments('id');
            $table->mediumInteger('num_ticket');
            $table->mediumInteger('num_ticket_store');
            $table->string('notes', 100)->nullable();
            $table->unsignedInteger('_created_by');
            $table->unsignedSmallInteger('_workpoint_from');
            $table->unsignedSmallInteger('_workpoint_to');
            $table->unsignedSmallInteger('_type');
            $table->unsignedSmallInteger('_status');
            $table->tinyInteger('printed');
            $table->time('time_life');
            $table->timestamps();

            $table->foreign('_created_by')->references('id')->on('accounts');
            $table->foreign('_workpoint_from')->references('id')->on('workpoints');
            $table->foreign('_workpoint_to')->references('id')->on('workpoints');
            $table->foreign('_type')->references('id')->on('type_requisition');
            $table->foreign('_status')->references('id')->on('requisition_process');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('requisition');
    }
}
