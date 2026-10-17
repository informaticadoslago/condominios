<?php

use App\Models\Inmueble;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cómo se nombra el inmueble en las cartas ("1ºA", "Bajo", "Piso 3º"). La ficha la
     * propone a partir de tipo, planta y puerta (Inmueble::sugerirNomenclatura), pero se
     * puede cambiar. A los que ya existen se les pone la propuesta.
     */
    public function up(): void
    {
        Schema::table('inmuebles', function (Blueprint $table) {
            $table->string('nomenclatura', 50)->nullable()->after('puerta');
        });

        $tipos = DB::table('tipo_inmuebles')->pluck('descripcion', 'id');

        DB::table('inmuebles')->orderBy('id')->each(function ($inmueble) use ($tipos) {
            DB::table('inmuebles')->where('id', $inmueble->id)->update([
                'nomenclatura' => Inmueble::sugerirNomenclatura($tipos[$inmueble->tipo_inmueble_id] ?? null, $inmueble->planta, $inmueble->puerta) ?: null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('inmuebles', function (Blueprint $table) {
            $table->dropColumn('nomenclatura');
        });
    }
};
