<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use App\Services\Recibos\GeneradorRecibos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Presupuesto extends Model
{
    use ConHistorialEstado;

    protected $table = 'presupuestos';

    protected $fillable = [
        'comunidad_id',
        'nombre',
        'anho',
        'estado_id',
        'numero_pagos',
        'fecha_primer_pago',
        'periodicidad_id',
    ];

    protected $casts = [
        'fecha_primer_pago' => 'date',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function estado()
    {
        return $this->belongsTo(TipoEstadoPresupuesto::class, 'estado_id');
    }

    public function periodicidad()
    {
        return $this->belongsTo(TipoPeriodicidadPago::class, 'periodicidad_id');
    }

    public function conceptos()
    {
        return $this->hasMany(ConceptoPresupuesto::class);
    }

    /**
     * Con un presupuesto de un año (12 meses) y una periodicidad de $meses entre pago y
     * pago, el número de pagos no es un dato aparte: sale de dividir uno entre otro.
     */
    public static function numeroPagosPara(int $meses): int
    {
        return max(1, intdiv(12, $meses));
    }

    /**
     * Reparte $total en $n pagos iguales. Si no es exacto, el céntimo de más de cada
     * pago va a los PRIMEROS pagos, no al último: un impago se nota antes en los pagos
     * más bajos (los que se han quedado sin el céntimo extra), que quedan al final.
     * Se trabaja en céntimos enteros para no arrastrar imprecisión de coma flotante.
     *
     * @return float[] uno por pago, en el mismo orden en que se cobran
     */
    public static function repartirPagos(float $total, int $n): array
    {
        if ($n <= 0) {
            return [];
        }

        $totalCentimos = (int) round($total * 100);
        $base          = intdiv($totalCentimos, $n);
        $sobran        = $totalCentimos - $base * $n; // 0 <= sobran < n

        return collect(range(1, $n))
            ->map(fn ($i) => ($base + ($i <= $sobran ? 1 : 0)) / 100)
            ->all();
    }

    /**
     * Reparte $total proporcionalmente a $pesos (p.ej. coeficientes de inmuebles dentro
     * de un grupo de reparto). MÉTODO: cada uno se lleva el resultado exacto redondeado
     * hacia ABAJO al céntimo; lo que sobra de ese redondeo se reparte de céntimo en
     * céntimo empezando por la posición $inicio dentro de $pesos (en el orden en que
     * vengan, dando la vuelta al llegar al final) — mismo criterio que repartirPagos():
     * el primero de turno se lleva el céntimo de más, el último se queda corto.
     *
     * $inicio es la rotación: un presupuesto (o derrama, que comparte la misma rotación
     * por grupo — ver avanzarRotacionReparto()) empieza donde se quedó el anterior, para
     * que a lo largo de los años no sean siempre los mismos los que se queden sin él.
     *
     * La suma del resultado es SIEMPRE exacta a $total.
     *
     * @param  array<int|string, float>  $pesos  clave (id de inmueble, o lo que sea) => peso relativo
     * @return array<int|string, float> misma clave => importe
     */
    public static function repartirProporcional(float $total, array $pesos, int $inicio = 0): array
    {
        $sumaPesos = array_sum($pesos);
        if ($sumaPesos <= 0) {
            return array_map(fn () => 0.0, $pesos);
        }

        $totalCentimos = (int) round($total * 100);
        $n             = count($pesos);

        $centimosEnteros = [];
        $asignados       = 0;
        foreach ($pesos as $clave => $peso) {
            $crudo                    = $totalCentimos * $peso / $sumaPesos;
            $centimosEnteros[$clave]  = (int) floor($crudo);
            $asignados               += $centimosEnteros[$clave];
        }

        $sobran = $totalCentimos - $asignados; // céntimos sueltos por el redondeo hacia abajo, 0 <= sobran < n

        $resultado = [];
        $i         = 0;
        foreach ($centimosEnteros as $clave => $centimos) {
            // Posición de esta clave relativa al inicio de la rotación (0 = la primera en cobrar el céntimo de más).
            $posicion           = ($i - $inicio + $n) % $n;
            $resultado[$clave]  = ($centimos + ($posicion < $sobran ? 1 : 0)) / 100;
            $i++;
        }

        return $resultado;
    }

    /** Cuántos céntimos sobran al repartir $total proporcionalmente a $pesos (sin repartirlos todavía). */
    public static function sobranteCentimos(float $total, array $pesos): int
    {
        $sumaPesos = array_sum($pesos);
        if ($sumaPesos <= 0) {
            return 0;
        }

        $totalCentimos = (int) round($total * 100);
        $asignados     = 0;
        foreach ($pesos as $peso) {
            $asignados += (int) floor($totalCentimos * $peso / $sumaPesos);
        }

        return $totalCentimos - $asignados;
    }

    /**
     * Al aprobar este presupuesto (ver booted()), cada grupo de reparto que use avanza
     * su "siguiente_inicio_reparto": la próxima vez (presupuesto del año que viene o una
     * derrama, da igual, comparten la misma rotación) empieza justo donde se quedó
     * ahora, así que quien no se llevó el céntimo de más esta vez es el primero la
     * próxima.
     */
    public function avanzarRotacionReparto(): void
    {
        $conceptos = $this->conceptos()->with('grupoDeReparto.inmuebles')->get()->groupBy('grupo_de_reparto_id');

        foreach ($conceptos as $conceptosDelGrupo) {
            $grupo = $conceptosDelGrupo->first()->grupoDeReparto;
            if (! $grupo) {
                continue;
            }

            $miembros = $grupo->inmuebles->sortBy(fn ($i) => [$i->planta, $i->puerta])->values();
            if ($miembros->isEmpty()) {
                continue;
            }

            $totalGrupo = (float) $conceptosDelGrupo->sum('importe');
            $pesos      = $miembros->mapWithKeys(fn ($i) => [$i->id => (float) ($i->pivot->coeficiente ?? $i->coeficiente)])->all();
            $sobran     = self::sobranteCentimos($totalGrupo, $pesos);

            $grupo->update([
                'siguiente_inicio_reparto' => ($grupo->siguiente_inicio_reparto + $sobran) % $miembros->count(),
            ]);
        }
    }

    public function recibos()
    {
        return $this->hasMany(Recibo::class);
    }

    protected static function booted(): void
    {
        static::updated(function (self $presupuesto) {
            if ($presupuesto->wasChanged('estado_id') && $presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
                // El orden no es indiferente: los recibos se vuelcan con el reparto tal
                // y como se aprobó, y solo después avanza la rotación para el siguiente.
                // Al revés, los recibos llevarían los céntimos ya movidos de sitio. Van
                // juntos en una transacción para que no pueda quedar la rotación avanzada
                // sin los recibos que la justifican.
                DB::transaction(function () use ($presupuesto) {
                    app(GeneradorRecibos::class)->generar($presupuesto);

                    $presupuesto->avanzarRotacionReparto();
                });
            }
        });
    }
}
