<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de documento ADJUNTO (DNI, libro de familia, matrícula…).
 * No confundir con TipoDocumentoIdentificativo (NIF/NIE/CIF/pasaporte de una persona).
 */
class TipoDocumento extends Model
{
    protected $table = 'tipo_documentos';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    const TIPO_OTROS = 1;

    const TIPO_PLANTILLA_DIPLOMA = 8;
}
