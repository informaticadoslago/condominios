<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Días de la semana, en numeración ISO (lunes = 1). Es la de Carbon, así que casar un
 * horario con una fecha es directo: $fecha->dayOfWeekIso == $dia->value.
 *
 * T-L9-L12: sustituye a los ids del cajón legacy 'tipos_de_tipos' (allí el lunes era el
 * 174, y cambiaba de una instalación a otra). En la columna tipo_diasemana_id de
 * horariosgrupos ya se guarda este número: L9 dejará de leer bien ese dato.
 */
enum DiaSemana: int
{
    case LUNES = 1;
    case MARTES = 2;
    case MIERCOLES = 3;
    case JUEVES = 4;
    case VIERNES = 5;
    case SABADO = 6;
    case DOMINGO = 7;

    /** El nombre lo pone Carbon, en el idioma de la aplicación. */
    public function nombre(): string
    {
        return ucfirst(Carbon::now()->startOfWeek()->addDays($this->value - 1)->isoFormat('dddd'));
    }

    /** El día de una fecha concreta (para saber qué grupos tienen clase ese día). */
    public static function deFecha(Carbon $fecha): self
    {
        return self::from($fecha->dayOfWeekIso);
    }

    /** ¿Se dan clases ese día? (ver defines.dias_lectivos). */
    public function esLectivo(): bool
    {
        return in_array($this->value, array_map('intval', config('defines.dias_lectivos', [1, 2, 3, 4, 5])), true);
    }

    /**
     * [1 => 'Lunes', …] para los desplegables. Por defecto solo los días lectivos: no se
     * cuadran grupos en fin de semana. Con $todos se ofrecen los siete (por ejemplo para
     * seguir mostrando un horario antiguo que sí cayera en sábado).
     */
    public static function opciones(bool $todos = false): array
    {
        return array_reduce(
            array_filter(self::cases(), fn (self $dia) => $todos || $dia->esLectivo()),
            fn (array $dias, self $dia) => $dias + [$dia->value => $dia->nombre()],
            [],
        );
    }
}
