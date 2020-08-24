<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('ticket', function (Blueprint $table) {
            $table->increments('id');
            $table->string('details', 80);
            $table->string('picture', 100);
            $table->unsignedSmallInteger('_report');
            $table->unsignedTinyInteger('_status');
            $table->unsignedInteger('_responsable');
            $table->unsignedInteger('_created_by');
            $table->foreign('_report')->references('id')->on('catalog_report');
            $table->foreign('_status')->references('id')->on('report_status');
            $table->foreign('_responsable')->references('id')->on('accounts');
            $table->foreign('_created_by')->references('id')->on('accounts');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('ticket');
    }
}
