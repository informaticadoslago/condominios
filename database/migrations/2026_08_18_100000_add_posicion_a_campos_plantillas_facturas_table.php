<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ancla alternativa por coordenadas en la página del PDF, para plantillas cuyas
 * etiquetas (Nº factura, Fecha, etc.) están "quemadas" en una imagen y no existen
 * como texto seleccionable: ni el marcado manual ni ExtractorPosicional tienen
 * nada a lo que anclarse. Convive con texto_ancla (que sigue siendo el mecanismo
 * normal); un campo usa uno u otro, nunca ambos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_plantillas_facturas', function (Blueprint $table) {
            $table->string('texto_ancla', 150)->nullable()->change();
            $table->unsignedSmallInteger('pagina')->nullable()->after('longitud_valor');
            $table->float('pos_x')->nullable()->after('pagina');
            $table->float('pos_y')->nullable()->after('pos_x');
            $table->float('pos_ancho')->nullable()->after('pos_y');
        });
    }

    public function down(): void
    {
        Schema::table('campos_plantillas_facturas', function (Blueprint $table) {
            $table->dropColumn(['pagina', 'pos_x', 'pos_y', 'pos_ancho']);
            $table->string('texto_ancla', 150)->nullable(false)->change();
        });
    }
};
