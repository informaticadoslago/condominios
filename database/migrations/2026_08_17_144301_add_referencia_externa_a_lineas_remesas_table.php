<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lineas_remesas', function (Blueprint $table) {
            // EndToEndId tal cual venía en el pain.008 de una remesa que no generamos
            // nosotros (importada de otro programa). En las remesas propias no hace
            // falta: se reconstruye al vuelo como "{referencia remesa}-{id línea}" (ver
            // FicheroRemesaSepa). Aquí no podemos reconstruirlo así porque el formato es
            // el de quien la presentó, así que se guarda literal para casar la devolución
            // por comparación exacta en vez de intentar volver a parsearlo.
            $table->string('referencia_externa', 35)->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('lineas_remesas', function (Blueprint $table) {
            $table->dropColumn('referencia_externa');
        });
    }
};
