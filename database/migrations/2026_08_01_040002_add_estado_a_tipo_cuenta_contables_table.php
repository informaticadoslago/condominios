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
        Schema::table('tipo_cuenta_contables', function (Blueprint $table) {
            $table->unsignedBigInteger('estado_id')->default(1)->after('descripcion')
                ->index('tipo_cuenta_contables_estado_id_foreign');
            $table->timestamps();

            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tipo_cuenta_contables', function (Blueprint $table) {
            $table->dropForeign(['estado_id']);
            $table->dropColumn(['estado_id', 'created_at', 'updated_at']);
        });
    }
};
