<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('last_transaction_date')->nullable();
            $table->integer('balance')->nullable();
            $table->string('created_date')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->json('phones')->nullable();
            $table->integer('yandex_profile_id')->nullable();
            $table->string('work_status')->nullable();
            $table->string('status')->default('Добавлено');
            $table->string('link')->nullable();
            $table->integer('transaction_id')->nullable();
            $table->string('current_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('profiles');
    }
}
