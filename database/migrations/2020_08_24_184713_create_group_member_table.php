<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupMemberTable extends Migration{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('group_member', function (Blueprint $table) {
            $table->unsignedSmallInteger('_work_team');
            $table->unsignedTinyInteger('_rol');
            $table->unsignedInteger('_account');
            $table->foreign('_work_team')->references('id')->on('work_team');
            $table->foreign('_rol')->references('id')->on('rol_support');
            $table->foreign('_account')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(){
        Schema::dropIfExists('group_member');
    }
}
