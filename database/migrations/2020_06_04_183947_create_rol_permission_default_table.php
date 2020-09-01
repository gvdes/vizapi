<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolPermissionDefaultTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('rol_permission_default', function (Blueprint $table) {
            $table->unsignedTinyInteger('_rol');
            $table->unsignedSmallInteger('_permission');

            $table->foreign('_rol')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('_permission')->references('id')->on('permissions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('rol_permission_default');
    }
}
