<?php

namespace App\Services\Horarios;

use App\Models\Examen;
use App\Models\Horario;
use Carbon\Carbon;

/**
 * Los exámenes de un horario en formato iCalendar (RFC 5545), para suscribirse desde
 * Google Calendar, el calendario del iPhone, Outlook… Las horas se guardan en hora local
 * (la zona de la aplicación) y aquí se pasan a UTC: así el fichero no tiene que declarar
 * la zona horaria y el cambio de hora cae donde toca.
 *
 * Cada examen lleva un UID fijo (su id): al volver a leer el fichero, el calendario
 * actualiza o quita el evento que ya tenía en vez de duplicarlo.
 */
class CalendarioExamenesIcs
{
    public function generar(Horario $horario): string
    {
        $examenes = Examen::where('horario_id', $horario->id)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $dominio = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        $lineas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$dominio.'//Examenes//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->texto(__('Exámenes :horario', ['horario' => $horario->nombre])),
        ];

        foreach ($examenes as $examen) {
            array_push($lineas,
                'BEGIN:VEVENT',
                'UID:examen-'.$examen->id.'@'.$dominio,
                'DTSTAMP:'.$this->utc($examen->updated_at ?? now()),
                'DTSTART:'.$this->utc($this->momento($examen->fecha, $examen->hora)),
                'DTEND:'.$this->utc($this->momento($examen->fecha, $examen->hora_fin)),
                'SUMMARY:'.$this->texto($examen->titulo),
            );

            // La alarma la respeta (o no) cada calendario: el del iPhone sí; Google, en los
            // calendarios suscritos, aplica las notificaciones que tenga configuradas cada uno.
            if ($examen->alarma_minutos) {
                array_push($lineas,
                    'BEGIN:VALARM',
                    'ACTION:DISPLAY',
                    'DESCRIPTION:'.$this->texto($examen->titulo),
                    'TRIGGER:'.$this->antelacion($examen->alarma_minutos),
                    'END:VALARM',
                );
            }

            $lineas[] = 'END:VEVENT';
        }

        $lineas[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $linea) => $this->plegar($linea), $lineas))."\r\n";
    }

    private function momento(Carbon $fecha, string $hora): Carbon
    {
        return Carbon::parse($fecha->toDateString().' '.$hora, config('app.timezone'));
    }

    private function utc(Carbon $momento): string
    {
        return $momento->copy()->utc()->format('Ymd\THis\Z');
    }

    /** Tantos minutos antes del inicio, en la unidad más grande que cuadre: -P1W, -P2D, -PT1H, -PT15M. */
    private function antelacion(int $minutos): string
    {
        return match (true) {
            $minutos % 10080 === 0 => '-P'.($minutos / 10080).'W',
            $minutos % 1440 === 0  => '-P'.($minutos / 1440).'D',
            $minutos % 60 === 0    => '-PT'.($minutos / 60).'H',
            default                => '-PT'.$minutos.'M',
        };
    }

    /** Escapa lo que el formato reserva en los textos: \ ; , y los saltos de línea. */
    private function texto(string $texto): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $texto);
    }

    /**
     * El formato no admite líneas de más de 75 octetos: se parten, y cada trozo de
     * continuación empieza con un espacio. Sin cortar por la mitad un carácter UTF-8.
     */
    private function plegar(string $linea): string
    {
        $trozos = [];

        while (strlen($linea) > 75) {
            $corte = 75;

            while ((ord($linea[$corte]) & 0xC0) === 0x80) {
                $corte--;
            }

            $trozos[] = substr($linea, 0, $corte);
            $linea = ' '.substr($linea, $corte);
        }

        $trozos[] = $linea;

        return implode("\r\n", $trozos);
    }
}
