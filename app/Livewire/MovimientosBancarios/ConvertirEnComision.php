<?php

namespace App\Livewire\MovimientosBancarios;

use App\Exceptions\AsientoInvalidoException;
use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\EjercicioCerradoException;
use App\Exceptions\EjercicioContableDesconocidoException;
use App\Models\ComisionBancaria;
use App\Models\Comunidad;
use App\Models\CuentaContable;
use App\Models\MovimientoBancario;
use App\Models\TipoComisionBancaria;
use App\Models\TipoMovimientoBancario;
use App\Services\ComisionesBancarias\RegistrarComisionBancariaService;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Convierte a mano UN movimiento en comisión bancaria. Si su TIPO OPERACIÓN todavía no
 * está en el catálogo (tipos_movimiento_bancario), se pregunta de qué tipo de comisión
 * es —uno ya existente de esta empresa, o uno nuevo con su propio nombre y cuenta de
 * gasto— y se da de alta ahí mismo: así la próxima comisión con ese mismo texto ya se
 * clasifica sola (ver ClasificarComisionesDesdeMovimientos).
 */
class ConvertirEnComision extends Component
{
    /** Sentinel de $tipoElegido para "está creando uno nuevo", no es un id real. */
    const NUEVO_TIPO = 'nuevo';

    public bool $abrir = false;
    public ?int $movimientoId = null;
    public ?string $error = null;

    /** Si el tipo ya estaba catalogado, se enseña de una vez y no se pregunta nada. */
    public ?string $tipoConocido = null;

    /** Id de un TipoComisionBancaria existente, o self::NUEVO_TIPO. Solo se usa cuando tipoConocido es null. */
    public string $tipoElegido = '';

    public string $nuevoNombre = '';
    public ?int $nuevaCuentaContableId = null;

    #[On('abrir-convertir-en-comision')]
    public function mostrar($movimientoId): void
    {
        $this->reset(['error', 'tipoConocido', 'tipoElegido', 'nuevoNombre', 'nuevaCuentaContableId']);
        $this->resetErrorBag();

        $this->movimientoId = (int) $movimientoId;
        $this->tipoConocido = $this->movimiento()?->tipoCatalogado()?->codigo;
        $this->tipoElegido  = $this->tiposComisionExistentes()->first()?->id ?? self::NUEVO_TIPO;
        $this->abrir = true;
    }

    private function movimiento(): ?MovimientoBancario
    {
        return MovimientoBancario::where('id', $this->movimientoId)
            ->whereHas('cuentaBancaria', fn ($q) => $q->where('titular_type', Comunidad::class)
                ->where('titular_id', session('comunidad_actual_id')))
            ->first();
    }

    private function empresaContableId(): ?int
    {
        $titular = $this->movimiento()?->cuentaBancaria?->titular;

        return $titular instanceof Comunidad ? $titular->empresa_contable_id : null;
    }

    /** Tipos de comisión ya dados de alta para esta empresa, para elegir entre ellos. */
    public function tiposComisionExistentes()
    {
        $empresaId = $this->empresaContableId();

        return $empresaId ? TipoComisionBancaria::where('empresa_contable_id', $empresaId)->orderBy('descripcion')->get() : collect();
    }

    /** Cuentas de gasto (grupo 6) de esta empresa, para la cuenta del tipo nuevo. */
    public function cuentasDeGasto()
    {
        $empresaId = $this->empresaContableId();

        return $empresaId
            ? CuentaContable::where('empresa_contable_id', $empresaId)
                ->where('estado_id', CuentaContable::ESTADO_ACTIVO)
                ->where('codigo', 'like', '6%')
                ->orderBy('codigo')
                ->get()
            : collect();
    }

    public function confirmar(RegistrarComisionBancariaService $servicio): void
    {
        $this->error = null;

        $movimiento = $this->movimiento();

        if (! $movimiento) {
            $this->error = __('Movimiento no encontrado.');

            return;
        }

        $cuentaBancaria = $movimiento->cuentaBancaria;
        $empresaId = $this->empresaContableId();

        if (! $empresaId) {
            $this->error = __('Esta comunidad no está enlazada con ninguna empresa contable.');

            return;
        }

        if ($this->tipoConocido) {
            $tipoComision = TipoComisionBancaria::where('empresa_contable_id', $empresaId)->where('codigo', $this->tipoConocido)->first();

            if (! $tipoComision) {
                $this->error = __('Falta configurar el tipo de comisión ":codigo" para esta empresa contable.', ['codigo' => $this->tipoConocido]);

                return;
            }
        } else {
            $tipoComision = $this->resolverTipoElegido($empresaId);

            if (! $tipoComision) {
                return; // el error ya lo ha dejado puesto resolverTipoElegido()
            }
        }

        if (! $this->tipoConocido) {
            // firstOrCreate: si dos usuarios lo dan de alta a la vez, el índice único
            // (entidad_bancaria_id, tipo_operacion, prefijo_descripcion) evita el duplicado.
            TipoMovimientoBancario::firstOrCreate([
                'entidad_bancaria_id' => $cuentaBancaria->entidad_bancaria_id,
                'tipo_operacion'      => $movimiento->tipo_operacion,
                'prefijo_descripcion' => null,
            ], [
                'codigo' => $tipoComision->codigo,
            ]);
        }

        $referencia = $this->extraerFra($movimiento->descripcion ?? '');
        $importe = abs((float) $movimiento->importe);

        if ($this->yaProcesada($cuentaBancaria->id, $movimiento->fecha_valor->format('Y-m-d'), $tipoComision->codigo, $referencia, $importe)) {
            $this->error = __('Ya existe una comisión bancaria con estos mismos datos.');

            return;
        }

        try {
            $servicio->registrar(
                cuentaBancariaId: $cuentaBancaria->id,
                tipoComisionBancariaId: $tipoComision->id,
                remesaId: null,
                fecha: $movimiento->fecha_valor->format('Y-m-d'),
                concepto: $movimiento->descripcion ?: $movimiento->tipo_operacion,
                referencia: $referencia,
                lineas: [['concepto' => $movimiento->descripcion ?: $movimiento->tipo_operacion, 'importe' => $importe]],
            );
        } catch (AsientoInvalidoException|EjercicioCerradoException|EjercicioContableDesconocidoException|CuentaContableDesconocidaException) {
            $this->dispatch('toast-error', ['title' => __('Comisión registrada, pero sin contabilizar (ejercicio o cuenta pendiente)')]);
            $this->cerrar();
            $this->dispatch('comision-bancaria-importada');

            return;
        }

        $this->dispatch('toast-success', ['title' => __('Comisión bancaria registrada')]);
        $this->cerrar();
        $this->dispatch('comision-bancaria-importada');
    }

    /** Un tipo ya existente elegido del select, o uno nuevo dado de alta con nombre y cuenta. */
    private function resolverTipoElegido(int $empresaId): ?TipoComisionBancaria
    {
        if ($this->tipoElegido !== self::NUEVO_TIPO) {
            $tipo = TipoComisionBancaria::where('empresa_contable_id', $empresaId)->find((int) $this->tipoElegido);

            if (! $tipo) {
                $this->error = __('Tipo de comisión no válido.');
            }

            return $tipo;
        }

        $nombre = trim($this->nuevoNombre);

        if ($nombre === '') {
            $this->error = __('Ponle un nombre al tipo de comisión nuevo.');

            return null;
        }

        if (! $this->nuevaCuentaContableId || ! $this->cuentasDeGasto()->firstWhere('id', $this->nuevaCuentaContableId)) {
            $this->error = __('Elige la cuenta de gasto del tipo de comisión nuevo.');

            return null;
        }

        return TipoComisionBancaria::create([
            'empresa_contable_id' => $empresaId,
            'codigo'              => $this->codigoLibre($empresaId, $nombre),
            'descripcion'         => $nombre,
            'cuenta_contable_id'  => $this->nuevaCuentaContableId,
        ]);
    }

    /** Slug del nombre, corto y único para esta empresa (tipo_comisiones_bancarias.codigo es unique por empresa). */
    private function codigoLibre(int $empresaId, string $nombre): string
    {
        $base = Str::slug($nombre, '_') ?: 'comision';
        $base = Str::limit($base, 16, '');

        $codigo = $base;
        $sufijo = 1;
        while (TipoComisionBancaria::where('empresa_contable_id', $empresaId)->where('codigo', $codigo)->exists()) {
            $codigo = Str::limit($base, 18, '').'_'.$sufijo++;
        }

        return $codigo;
    }

    /** Nº de FRA dentro de "LIQ. REM. 31-07-2026 FRA BI 50252026071000345" (o FRA IVA), si lo hay. */
    private function extraerFra(string $descripcion): ?string
    {
        return preg_match('/FRA\s+(?:BI|IVA)\s+(\S+)/i', $descripcion, $m) ? $m[1] : null;
    }

    private function yaProcesada(int $cuentaBancariaId, string $fecha, string $codigo, ?string $referencia, float $importe): bool
    {
        if ($referencia !== null) {
            return ComisionBancaria::where('cuenta_bancaria_id', $cuentaBancariaId)
                ->where('referencia', $referencia)
                ->exists();
        }

        return ComisionBancaria::where('cuenta_bancaria_id', $cuentaBancariaId)
            ->where('fecha', $fecha)
            ->whereHas('tipoComisionBancaria', fn ($q) => $q->where('codigo', $codigo))
            ->with('lineas')
            ->get()
            ->contains(fn (ComisionBancaria $c) => round((float) $c->lineas->sum('importe'), 2) === round($importe, 2));
    }

    public function cerrar(): void
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.movimientos-bancarios.convertir-en-comision', [
            'movimiento' => $this->movimiento(),
        ]);
    }
}
