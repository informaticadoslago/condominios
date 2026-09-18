<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

class Asignatura extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    protected $table = 'asignaturas';

    protected $fillable = [
        'horario_id',
        'nombre',
        'estado_id',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    /**
     * Paleta fija de tonos suaves para pintar cada asignatura en la rejilla (pantalla y
     * PDF) sin tener que guardar un color: el mismo id siempre cae en el mismo tono.
     */
    private const PALETA = [
        ['fondo' => '#FDE2E2', 'texto' => '#7A1F1F'],
        ['fondo' => '#DCEAFB', 'texto' => '#1F3A5F'],
        ['fondo' => '#DFF5E1', 'texto' => '#1F5C33'],
        ['fondo' => '#FFF3CD', 'texto' => '#7A5B00'],
        ['fondo' => '#EAE0F8', 'texto' => '#4B2E7A'],
        ['fondo' => '#FDE6D0', 'texto' => '#7A3F0A'],
        ['fondo' => '#D9F2F0', 'texto' => '#0F5C56'],
        ['fondo' => '#FBE1F0', 'texto' => '#7A1F55'],
        ['fondo' => '#EAF3D0', 'texto' => '#4C5C0F'],
        ['fondo' => '#E0E3FB', 'texto' => '#2C2F7A'],
        ['fondo' => '#FCE9D6', 'texto' => '#7A4A1F'],
        ['fondo' => '#D6F3FB', 'texto' => '#0F5A7A'],
    ];

    public function colorFondo(): string
    {
        return self::PALETA[$this->id % count(self::PALETA)]['fondo'];
    }

    public function colorTexto(): string
    {
        return self::PALETA[$this->id % count(self::PALETA)]['texto'];
    }
}
