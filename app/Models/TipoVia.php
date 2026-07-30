<?php

namespace App\Models;

use App\Models\Traits\Ordenable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoVia extends Model
{
    use HasFactory, Ordenable;

    public $timestamps = false;

    protected $table = 'tiposdevias';

    protected $fillable = [
        'codigo1', 'codigo2', 'nombre',
    ];

    public function vias()
    {
        return $this->hasMany(Via::class, 'tipodevia_id');
    }
}
