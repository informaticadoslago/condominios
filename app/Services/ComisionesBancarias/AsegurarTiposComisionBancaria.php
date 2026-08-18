<?php

namespace App\Services\ComisionesBancarias;

use App\Models\CuentaContable;
use App\Models\EmpresaContable;
use App\Models\TipoComisionBancaria;
use App\Models\TipoCuentaContable;

/**
 * Da a una empresa contable sus filas de tipo_comisiones_bancarias (remesa,
 * mantenimiento y devolución) con su cuenta ya resuelta, dando de alta lo que falte del
 * grupo 626.
 *
 * Se llama al enlazar una comunidad nueva y, con las que ya existían, desde la
 * migración que dio de alta esto. Repetirlo no duplica ni pisa: la fila que ya exista
 * (con su cuenta, puesta a mano o no) se deja como está.
 */
final class AsegurarTiposComisionBancaria
{
    public function ejecutar(EmpresaContable $empresa): void
    {
        $remesa = $this->asegurarTipo($empresa, TipoComisionBancaria::REMESA, 'Comisión de remesa');
        $this->asegurarTipo($empresa, TipoComisionBancaria::MANTENIMIENTO, 'Mantenimiento y administración de cuenta');

        // Misma cuenta que la de remesa: es donde también se abona lo que se repercute
        // a los propietarios (ver EnlazarCobrosContabilidad::cuentaGastosBancarios), así
        // que ahí queda neteado lo repercutido contra lo que cobra el banco de verdad.
        if (! TipoComisionBancaria::where('empresa_contable_id', $empresa->id)->where('codigo', TipoComisionBancaria::DEVOLUCION)->exists()) {
            TipoComisionBancaria::create([
                'empresa_contable_id' => $empresa->id,
                'codigo'              => TipoComisionBancaria::DEVOLUCION,
                'descripcion'         => 'Comisión de devolución',
                'cuenta_contable_id'  => $remesa->cuenta_contable_id,
            ]);
        }
    }

    private function asegurarTipo(EmpresaContable $empresa, string $codigo, string $descripcion): TipoComisionBancaria
    {
        $existente = TipoComisionBancaria::where('empresa_contable_id', $empresa->id)->where('codigo', $codigo)->first();

        if ($existente) {
            return $existente;
        }

        $cuenta = $this->resolverCuenta($empresa, $codigo);

        return TipoComisionBancaria::create([
            'empresa_contable_id' => $empresa->id,
            'codigo'              => $codigo,
            'descripcion'         => $descripcion,
            'cuenta_contable_id'  => $cuenta->id,
        ]);
    }

    /**
     * Da de alta lo que falte del grupo 626 y decide qué cuenta le toca a cada tipo. Si
     * la 62600001 ya existía con otro significado —en la primera comunidad real es
     * "COMISIONES DE MANTENIMIENTO Y ADMINISTRACIÓN DE CUENTA", nada que ver con
     * remesas— se reconoce por el nombre y se le deja esa; la de remesa se da de alta
     * aparte, dedicada.
     */
    private function resolverCuenta(EmpresaContable $empresa, string $codigo): CuentaContable
    {
        $grupo = CuentaContable::firstOrCreate(
            ['empresa_contable_id' => $empresa->id, 'codigo' => '62600000'],
            ['tipo_cuenta_contable_id' => TipoCuentaContable::GASTO, 'nombre' => 'Servicios bancarios'],
        );

        $existente = CuentaContable::where('empresa_contable_id', $empresa->id)->where('codigo', '62600001')->first();
        $esNuestra = $existente && $existente->nombre === 'Comisiones bancarias';

        if ($codigo === TipoComisionBancaria::REMESA) {
            $cuenta = ($existente && ! $esNuestra)
                ? CuentaContable::create([
                    'empresa_contable_id'     => $empresa->id,
                    'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO,
                    'cuenta_padre_id'         => $grupo->id,
                    'codigo'                  => $this->primerCodigoLibre($empresa->id),
                    'nombre'                  => 'Comisiones bancarias',
                ])
                : CuentaContable::firstOrCreate(
                    ['empresa_contable_id' => $empresa->id, 'codigo' => '62600001'],
                    ['tipo_cuenta_contable_id' => TipoCuentaContable::GASTO, 'cuenta_padre_id' => $grupo->id, 'nombre' => 'Comisiones bancarias'],
                );
        } else {
            // Mantenimiento: si la 62600001 ya era ajena, es ella. Si es nuestra (o no
            // existe), se da de alta la 62600002 dedicada.
            $cuenta = ($existente && ! $esNuestra)
                ? $existente
                : CuentaContable::firstOrCreate(
                    ['empresa_contable_id' => $empresa->id, 'codigo' => '62600002'],
                    [
                        'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO,
                        'cuenta_padre_id'         => $grupo->id,
                        'nombre'                  => 'Comisiones de mantenimiento y administración de cuenta',
                    ],
                );
        }

        CuentaContable::recolgarPlan($empresa->id);

        return $cuenta;
    }

    /** El primer 6260000X (01-99) que no exista ya en esa empresa. */
    private function primerCodigoLibre(int $empresaId): string
    {
        $existentes = CuentaContable::where('empresa_contable_id', $empresaId)
            ->where('codigo', 'like', '626000%')
            ->pluck('codigo')
            ->all();

        for ($n = 1; $n <= 99; $n++) {
            $codigo = '626000'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);

            if (! in_array($codigo, $existentes, true)) {
                return $codigo;
            }
        }

        throw new \RuntimeException('No queda ningún código libre en el grupo 626000.');
    }
}
