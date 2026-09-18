<?php

namespace App\Models;

use App\Support\DiaSemana;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class HorarioDia extends Model
{
    protected $table = 'horario_dias';

    protected $fillable = [
        'horario_id',
        'dia_semana',
        'hora_primera_sesion',
        'num_sesiones',
        'recreo_antes_de_sesion',
        'recreo_duracion_minutos',
    ];

    protected $casts = [
        'dia_semana'              => 'integer',
        'num_sesiones'            => 'integer',
        'recreo_antes_de_sesion'  => 'integer',
        'recreo_duracion_minutos' => 'integer',
    ];

    /**
     * Arranques de las dos franjas fijas del folio: mañana (8-15) y tarde (15-22). Un
     * día es "de mañana" si su primera sesión es antes de las 14 y "de tarde" si es a
     * partir de las 15; entre medias (14-15) no se clasifica y no se realinea.
     */
    private const FRANJA_MANANA_INICIO = '08:00';
    private const FRANJA_MANANA_LIMITE = '14:00';
    private const FRANJA_TARDE_INICIO  = '15:00';

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function diaSemana(): DiaSemana
    {
        return DiaSemana::from($this->dia_semana);
    }

    public function tieneRecreo(): bool
    {
        return ! is_null($this->recreo_antes_de_sesion) && ! is_null($this->recreo_duracion_minutos);
    }

    /**
     * La secuencia cronológica del día: una entrada por sesión más, si hay recreo, una
     * entrada de recreo justo antes de la sesión que le corresponda. Es la base para
     * pintar la rejilla (pantalla, modal de edición y PDF): la fila ya no es "la sesión
     * N" (el recreo no cuenta como sesión y puede caer en un sitio distinto cada día),
     * sino "la posición N en el orden del día".
     *
     * @return array<int, array{tipo: 'sesion', numero: int, hora: string}|array{tipo: 'recreo', hora: string, duracion: int}>
     */
    public function calcularSlots(int $duracionSesionMinutos): array
    {
        $slots = [];
        $hora  = Carbon::createFromFormat('H:i:s', $this->hora_primera_sesion);

        for ($s = 1; $s <= $this->num_sesiones; $s++) {
            if ($this->tieneRecreo() && $this->recreo_antes_de_sesion === $s) {
                $slots[] = [
                    'tipo'     => 'recreo',
                    'hora'     => $hora->format('H:i'),
                    'duracion' => $this->recreo_duracion_minutos,
                ];

                $hora = $hora->copy()->addMinutes($this->recreo_duracion_minutos);
            }

            $slots[] = [
                'tipo'   => 'sesion',
                'numero' => $s,
                'hora'   => $hora->format('H:i'),
            ];

            $hora = $hora->copy()->addMinutes($duracionSesionMinutos);
        }

        return $slots;
    }

    /** 'manana', 'tarde', o null si empieza entre las 14:00 y las 15:00 (caso ambiguo). */
    public function tipoJornada(): ?string
    {
        $hora = substr($this->hora_primera_sesion, 0, 5);

        if ($hora >= self::FRANJA_TARDE_INICIO) {
            return 'tarde';
        }

        if ($hora < self::FRANJA_MANANA_LIMITE) {
            return 'manana';
        }

        return null;
    }

    private function minutosDesdeMedianoche(string $hora): int
    {
        [$horas, $minutos] = explode(':', substr($hora, 0, 5));

        return ((int) $horas) * 60 + (int) $minutos;
    }

    /**
     * Cuántas filas en blanco hay que dejar antes de la primera sesión del día, para que
     * las columnas se alineen por hora real en vez de por "cada día empieza en la fila
     * 0": si un día de tarde empieza a las 15:00 (el arranque de la franja) y otro a las
     * 15:50, con sesiones de 50 minutos el segundo cae en la 2ª fila, no en la 1ª.
     */
    public function desfaseFilas(int $duracionSesionMinutos): int
    {
        $tipo = $this->tipoJornada();

        if (! $tipo || $duracionSesionMinutos <= 0) {
            return 0;
        }

        $inicioFranja  = $tipo === 'tarde' ? self::FRANJA_TARDE_INICIO : self::FRANJA_MANANA_INICIO;
        $minutosFranja = $this->minutosDesdeMedianoche($inicioFranja);
        $minutosDia    = $this->minutosDesdeMedianoche($this->hora_primera_sesion);

        return max(0, (int) round(($minutosDia - $minutosFranja) / $duracionSesionMinutos));
    }

    /** calcularSlots(), pero con las filas en blanco iniciales del desfase ya puestas. */
    public function calcularSlotsAlineados(int $duracionSesionMinutos): array
    {
        $desfase = $this->desfaseFilas($duracionSesionMinutos);

        return array_merge(array_fill(0, $desfase, null), $this->calcularSlots($duracionSesionMinutos));
    }
}
