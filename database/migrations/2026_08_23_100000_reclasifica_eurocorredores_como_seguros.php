<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige la factura de Eurocorredores del 28/05/2026 (718,51 €), que se contabilizó
 * contra 62300000 (Profesionales) en vez de la cuenta de seguros, porque el proveedor
 * no tenía un tipo "Seguros" al que asignarse. Localiza cada fila por su contenido, no
 * por id, porque los ids de desarrollo y producción no coinciden.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            if (! DB::table('tipo_proveedores')->where('id', 5)->exists()) {
                DB::table('tipo_proveedores')->insert([
                    'id'           => 5,
                    'descripcion'  => 'Seguros',
                    'cuenta_gasto' => '62500001',
                    'estado_id'    => 1,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $proveedor = DB::table('proveedores')
                ->join('personas_comunidad', 'personas_comunidad.id', '=', 'proveedores.persona_comunidad_id')
                ->where(function ($q) {
                    $q->where('personas_comunidad.razon_social', 'like', '%Eurocorredores%')
                        ->orWhere('personas_comunidad.nombre_comercial', 'like', '%Eurocorredores%');
                })
                ->select('proveedores.id')
                ->get();

            if ($proveedor->count() !== 1) {
                throw new \RuntimeException('Se esperaba un único proveedor Eurocorredores, encontrados: '.$proveedor->count());
            }
            $proveedorId = $proveedor->first()->id;

            DB::table('proveedores')->where('id', $proveedorId)->update(['tipo_proveedor_id' => 5]);

            $factura = DB::table('facturas_proveedores')
                ->where('proveedor_id', $proveedorId)
                ->where('fecha_factura', '28/05/2026')
                ->where('importe', 718.51)
                ->get();

            if ($factura->count() !== 1) {
                throw new \RuntimeException('Se esperaba una única factura de Eurocorredores del 28/05/2026 por 718,51 €, encontradas: '.$factura->count());
            }
            $factura = $factura->first();

            DB::table('facturas_proveedores')->where('id', $factura->id)->update(['cuenta_gasto' => '62500001']);

            $cuentaSeguro = DB::table('cuenta_contables')->where('codigo', '62500001')->first();
            if (! $cuentaSeguro) {
                throw new \RuntimeException('No existe la cuenta 62500001 (SEGURO EDIFICIO) en el plan de cuentas.');
            }

            if ($factura->asiento_contable === null) {
                throw new \RuntimeException('La factura no tiene asiento contable asociado.');
            }

            $apunte = DB::table('apunte_contables')
                ->where('asiento_contable_id', $factura->asiento_contable)
                ->where('debe', (int) round($factura->importe * 100))
                ->get();

            if ($apunte->count() !== 1) {
                throw new \RuntimeException('Se esperaba un único apunte al debe por el importe de la factura, encontrados: '.$apunte->count());
            }

            DB::table('apunte_contables')->where('id', $apunte->first()->id)->update(['cuenta_contable_id' => $cuentaSeguro->id]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $factura = DB::table('facturas_proveedores')
                ->where('cuenta_gasto', '62500001')
                ->where('fecha_factura', '28/05/2026')
                ->where('importe', 718.51)
                ->first();

            if ($factura) {
                DB::table('facturas_proveedores')->where('id', $factura->id)->update(['cuenta_gasto' => '62300000']);

                $cuentaProfesionales = DB::table('cuenta_contables')->where('codigo', '62300000')->first();

                if ($cuentaProfesionales && $factura->asiento_contable !== null) {
                    DB::table('apunte_contables')
                        ->where('asiento_contable_id', $factura->asiento_contable)
                        ->where('debe', (int) round($factura->importe * 100))
                        ->update(['cuenta_contable_id' => $cuentaProfesionales->id]);
                }

                DB::table('proveedores')->where('id', $factura->proveedor_id)->update(['tipo_proveedor_id' => 2]);
            }

            DB::table('tipo_proveedores')->where('id', 5)->delete();
        });
    }
};
