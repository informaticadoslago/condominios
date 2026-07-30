<?php

namespace App\Models;

use App\Models\Traits\Ordenable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Via extends Model
{
    use HasFactory, Ordenable;

    public $timestamps = false;

    protected $table = 'vias';

    protected $fillable = [
        'municipio_id', 'codigo', 'tipodevia_id', 'posiciontvia', 'nombre', 'nombrecorto',
    ];

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function tipoVia()
    {
        return $this->belongsTo(TipoVia::class, 'tipodevia_id');
    }

    public function scopeDeMunicipio(Builder $query, $municipio_id): void
    {
        $query->where('municipio_id', $municipio_id);
    }

    public function scopeBuscarNombre(Builder $query, ?string $texto): void
    {
        $texto = trim((string) $texto);
        if ($texto !== '') {
            $query->where('nombre', 'like', '%' . $texto . '%');
        }
    }
}
