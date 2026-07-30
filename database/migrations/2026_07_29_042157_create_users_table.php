<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('login', 191)->unique();
            $table->string('email', 191)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 191);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->rememberToken();
            $table->text('comentarios')->nullable();
            $table->boolean('estado')->default(true);
            $table->unsignedBigInteger('estado_id')->default(2)->index('users_estado_id_foreign');
            $table->timestamps();
            $table->unsignedBigInteger('persona_id')->nullable()->index('users_persona_id_foreign');
            $table->string('telefono_movil', 20)->nullable();
            $table->string('pais_movil', 5)->default('+34');
            $table->string('token_verificacion', 100)->nullable();
            $table->timestamp('tokenSent_at')->nullable();
            $table->unsignedSmallInteger('token_sent_count')->default(0);
            $table->timestamp('last_login_time')->nullable();
            $table->string('last_login_ip', 20)->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('last_seen')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
