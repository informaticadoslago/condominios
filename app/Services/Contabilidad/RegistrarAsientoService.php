<?php

namespace App\Services\Contabilidad;

use App\Exceptions\AsientoInvalidoException;
use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\EjercicioCerradoException;
use App\Exceptions\EjercicioContableDesconocidoException;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use App\Models\EjercicioContable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Única puerta de entrada de asientos a la contabilidad.
 *
 * Todas las reglas contables viven aquí, no en el controlador ni en el formulario: quien
 * entra por HTTP y quien llama a este servicio desde dentro de la aplicación pasan por
 * las mismas comprobaciones. Si el cuadre se validara en el FormRequest, cualquier
 * llamada interna se lo saltaría, y por ahí es por donde se cuela un asiento descuadrado.
 */
final class RegistrarAsientoService
{
    public function __construct(private readonly ResolvedorCuentasService $resolvedor)
    {
    }

    public function ejecutar(DatosAsiento $datos): AsientoContable
    {
        $this->validarPartidaDoble($datos);

        try {
            return DB::transaction(fn (): AsientoContable => $this->registrar($datos));
        } catch (QueryException $e) {
            // Dos peticiones idénticas a la vez: la que pierde choca contra el índice de
            // idempotencia. El asiento existe, que es lo que quería quien llamó.
            if ($datos->tieneReferencia() && $this->esClaveDuplicada($e)) {
                if ($existente = $this->asientoDeLaReferencia($datos)) {
                    return $existente;
                }
            }

            throw $e;
        }
    }

    /**
     * Se comprueba antes de abrir transacción y antes de tocar la base de datos: son
     * reglas de la propia estructura del asiento, no dependen de nada almacenado.
     */
    private function validarPartidaDoble(DatosAsiento $datos): void
    {
        if (count($datos->lineas) < 2) {
            throw new AsientoInvalidoException('Un asiento necesita al menos dos líneas.');
        }

        foreach ($datos->lineas as $i => $linea) {
            $n = $i + 1;

            if (($linea->cuenta === null) === ($linea->tercero === null)) {
                throw new AsientoInvalidoException("La línea $n tiene que indicar una cuenta o un tercero, y solo uno de los dos.");
            }

            if ($linea->debe < 0 || $linea->haber < 0) {
                throw new AsientoInvalidoException("La línea $n lleva un importe negativo; en partida doble se cambia de columna.");
            }

            if ($linea->debe > 0 && $linea->haber > 0) {
                throw new AsientoInvalidoException("La línea $n no puede llevar importe en el Debe y en el Haber a la vez.");
            }

            if ($linea->debe === 0 && $linea->haber === 0) {
                throw new AsientoInvalidoException("La línea $n no lleva ningún importe.");
            }
        }

        // Céntimos enteros: la igualdad es exacta, sin redondeos ni tolerancias.
        if ($datos->totalDebe() !== $datos->totalHaber()) {
            throw new AsientoInvalidoException(sprintf(
                'El asiento no cuadra: %s de Debe frente a %s de Haber.',
                number_format($datos->totalDebe() / 100, 2),
                number_format($datos->totalHaber() / 100, 2),
            ));
        }
    }

    private function registrar(DatosAsiento $datos): AsientoContable
    {
        // Reenviar el mismo hecho no duplica: devuelve el asiento que ya se hizo.
        if ($datos->tieneReferencia() && $existente = $this->asientoDeLaReferencia($datos)) {
            return $existente;
        }

        $ejercicio = $this->ejercicioAbierto($datos);
        $cuentas   = $this->resolverCuentas($datos);

        $asiento = AsientoContable::create([
            'empresa_contable_id'   => $datos->empresaContableId,
            'ejercicio_contable_id' => $ejercicio->id,
            'numero'                => $this->siguienteNumero($ejercicio),
            'fecha'                 => $datos->fecha,
            'diario'                => $datos->diario,
            'concepto'              => $datos->concepto,
            'referencia_tipo'       => $datos->referenciaTipo,
            'referencia_id'         => $datos->referenciaId,
            'evento'                => $datos->evento,
        ]);

        foreach ($datos->lineas as $i => $linea) {
            $asiento->apuntesContables()->create([
                'cuenta_contable_id' => $cuentas[$i]->id,
                'debe'               => $linea->debe,
                'haber'              => $linea->haber,
                'concepto'           => $linea->concepto,
            ]);
        }

        return $asiento;
    }

    private function ejercicioAbierto(DatosAsiento $datos): EjercicioContable
    {
        $ejercicio = EjercicioContable::where('empresa_contable_id', $datos->empresaContableId)
            ->where('nombre', $datos->ejercicio)
            ->first();

        if (! $ejercicio) {
            throw new EjercicioContableDesconocidoException(
                "No existe el ejercicio «{$datos->ejercicio}» en esa empresa contable."
            );
        }

        if ($ejercicio->cerrado) {
            throw new EjercicioCerradoException("El ejercicio «{$ejercicio->nombre}» está cerrado.");
        }

        if ($datos->fecha < $ejercicio->fecha_inicio->toDateString() || $datos->fecha > $ejercicio->fecha_fin->toDateString()) {
            throw new AsientoInvalidoException(
                "La fecha {$datos->fecha} cae fuera del ejercicio «{$ejercicio->nombre}»."
            );
        }

        return $ejercicio;
    }

    /**
     * Cuentas de cada línea, indexadas por su posición en el asiento. Las indicadas por
     * código se buscan de una vez; las indicadas por tercero pasan por el resolvedor.
     *
     * @return array<int, CuentaContable>
     */
    private function resolverCuentas(DatosAsiento $datos): array
    {
        $codigos = [];

        foreach ($datos->lineas as $linea) {
            if ($linea->cuenta !== null) {
                $codigos[$linea->cuenta] = true;
            }
        }

        $porCodigo = CuentaContable::where('empresa_contable_id', $datos->empresaContableId)
            ->whereIn('codigo', array_keys($codigos))
            ->get()
            ->keyBy('codigo');

        $faltan = array_diff(array_keys($codigos), $porCodigo->keys()->all());

        if ($faltan !== []) {
            throw new CuentaContableDesconocidaException(
                'No existen en esa empresa contable las cuentas: '.implode(', ', $faltan).'.'
            );
        }

        $cuentas = [];

        foreach ($datos->lineas as $i => $linea) {
            $cuentas[$i] = $linea->cuenta !== null
                ? $porCodigo[$linea->cuenta]
                : $this->resolvedor->resolver($datos->empresaContableId, $linea->tercero, $datos->crearTercerosDesconocidos);
        }

        return $cuentas;
    }

    private function siguienteNumero(EjercicioContable $ejercicio): int
    {
        // Bloquea la fila del ejercicio: sin esto, dos asientos simultáneos leen el mismo
        // máximo, calculan el mismo número y uno choca contra el único (ejercicio, numero).
        EjercicioContable::whereKey($ejercicio->id)->lockForUpdate()->first();

        return (int) AsientoContable::where('ejercicio_contable_id', $ejercicio->id)->max('numero') + 1;
    }

    private function asientoDeLaReferencia(DatosAsiento $datos): ?AsientoContable
    {
        return AsientoContable::where('empresa_contable_id', $datos->empresaContableId)
            ->where('referencia_tipo', $datos->referenciaTipo)
            ->where('referencia_id', $datos->referenciaId)
            ->where('evento', $datos->evento)
            ->first();
    }

    private function esClaveDuplicada(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
