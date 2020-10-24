<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountsTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nick', 45)->unique();
            $table->string('password', 100);
            $table->string('picture', 50);
            $table->string('names', 45);
            $table->string('surname_pat', 45);
            $table->string('surname_mat', 45)->nullable();
            $table->boolean('change_password');
            $table->rememberToken();
            $table->unsignedSmallInteger('_wp_principal');
            $table->unsignedTinyInteger('_rol');
            $table->timestamps();
            $table->foreign('_wp_principal')->references('id')->on('workpoints');
            $table->foreign('_rol')->references('id')->on('roles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('accounts');
    }
}
