<?php

use App\Models\Horario;
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
        // El código secreto de la dirección .ics del calendario de exámenes del horario
        // (ver ExamenesIcsController): la URL es pública, y este código hace de llave.
        Schema::table('horarios', function (Blueprint $table) {
            $table->string('token_calendario', 40)->nullable()->unique()->after('alinear_horas');
        });

        // Los horarios que ya había, cada uno con el suyo.
        DB::table('horarios')->pluck('id')->each(fn ($id) => DB::table('horarios')
            ->where('id', $id)
            ->update(['token_calendario' => Horario::nuevoTokenCalendario()]));

        Schema::table('horarios', function (Blueprint $table) {
            $table->string('token_calendario', 40)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            $table->dropUnique(['token_calendario']);
            $table->dropColumn('token_calendario');
        });
    }
};
