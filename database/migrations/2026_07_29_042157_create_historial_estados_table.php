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
        Schema::create('historial_estados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('estadoable_type');
            $table->unsignedBigInteger('estadoable_id');
            $table->unsignedBigInteger('estado_anterior')->nullable();
            $table->unsignedBigInteger('estado_nuevo');
            $table->unsignedBigInteger('user_id')->nullable()->index('historial_estados_user_id_foreign');
            $table->string('motivo')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['estadoable_type', 'estadoable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_estados');
    }
};
