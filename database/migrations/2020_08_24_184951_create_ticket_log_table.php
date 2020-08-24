<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketLogTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('ticket_log', function (Blueprint $table) {
            $table->id();
            $table->json('details');
            $table->unsignedInteger('_ticket');
            $table->timestamps();
            $table->foreign('_ticket')->references('id')->on('ticket');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('ticket_log');
    }
}
