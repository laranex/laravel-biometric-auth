<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('biometrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('authenticable_id');
            $table->string('authenticable_type');
            $table->longText('public_key');
            $table->string('challenge')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('biometrics');
    }
};
