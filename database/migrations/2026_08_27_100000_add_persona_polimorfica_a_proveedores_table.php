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
            $table->string('persona_type')->nullable()->after('persona_comunidad_id');
            $table->unsignedBigInteger('persona_id')->nullable()->after('persona_type');
            $table->index(['persona_type', 'persona_id']);
        });

        // Todos los proveedores existentes son, hoy, de comunidad.
        DB::table('proveedores')->update([
            'persona_type' => \App\Models\PersonaComunidad::class,
            'persona_id'   => DB::raw('persona_comunidad_id'),
        ]);

        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('persona_type')->nullable(false)->change();
            $table->unsignedBigInteger('persona_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropIndex(['persona_type', 'persona_id']);
            $table->dropColumn(['persona_type', 'persona_id']);
        });
    }
};
