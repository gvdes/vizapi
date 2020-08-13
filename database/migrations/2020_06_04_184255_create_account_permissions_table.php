<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountPermissionsTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('account_permissions', function (Blueprint $table) {
            $table->unsignedInteger('_account');
            $table->unsignedSmallInteger('_permission');
            
            $table->foreign('_account')->references('id')->on('account_workpoints')->onDelete('cascade');
            $table->foreign('_permission')->references('id')->on('permissions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('account_permissions');
    }
}
