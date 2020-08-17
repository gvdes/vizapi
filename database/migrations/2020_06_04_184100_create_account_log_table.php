<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountLogTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('account_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('_log_type');
            $table->unsignedInteger('_accto');
            $table->json('details');
            $table->timestamps();

            $table->foreign('_log_type')->references('id')->on('account_log_types')->onDelete('cascade');
            $table->foreign('_accto')->references('id')->on('accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('account_log');
    }
}
