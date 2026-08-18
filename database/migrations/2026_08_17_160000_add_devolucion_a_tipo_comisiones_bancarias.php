<?php

use App\Models\EmpresaContable;
use App\Services\ComisionesBancarias\AsegurarTiposComisionBancaria;
use Illuminate\Database\Migrations\Migration;
use App\Models\TipoComisionBancaria;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A las empresas que ya existían les falta la fila "devolucion" de
     * tipo_comisiones_bancarias (AsegurarTiposComisionBancaria ahora también la
     * asegura, pero solo para lo que se enlace de aquí en adelante). Sin esta fila, el
     * importador de comisiones bancarias descarta en silencio cualquier comisión de
     * devolución reconocida en el extracto: no encuentra a qué tipo asignarla.
     */
    public function up(): void
    {
        $servicio = new AsegurarTiposComisionBancaria();

        foreach (EmpresaContable::all() as $empresa) {
            $servicio->ejecutar($empresa);
        }
    }

    public function down(): void
    {
        DB::table('tipo_comisiones_bancarias')->where('codigo', TipoComisionBancaria::DEVOLUCION)->delete();
    }
};
