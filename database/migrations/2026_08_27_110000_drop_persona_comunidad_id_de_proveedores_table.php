<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropForeign('proveedores_persona_comunidad_id_foreign');
            $table->dropColumn('persona_comunidad_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->unsignedBigInteger('persona_comunidad_id')->nullable()
                ->after('id')->index('proveedores_persona_comunidad_id_foreign');
        });

        DB::table('proveedores')
            ->where('persona_type', \App\Models\PersonaComunidad::class)
            ->update(['persona_comunidad_id' => DB::raw('persona_id')]);

        Schema::table('proveedores', function (Blueprint $table) {
            $table->foreign('persona_comunidad_id')->references('id')->on('personas_comunidad')
                ->onUpdate('restrict')->onDelete('restrict');
        });
    }
};
