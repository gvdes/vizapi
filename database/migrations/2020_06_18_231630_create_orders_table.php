<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->smallInteger('num_ticket');
            $table->string('name', 35);
            $table->tinyInteger('printed')->default(false);
            $table->unsignedInteger('_created_by');
            $table->unsignedSmallInteger('_workpoint_from');
            $table->time('time_life');
            $table->timestamps();
            $table->foreign('_created_by')->references('_account')->on('account_workpoints');
            $table->foreign('_workpoint_from')->references('id')->on('workpoints');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('orders');
    }
}
