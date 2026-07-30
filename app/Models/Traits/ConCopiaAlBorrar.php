<?php

namespace App\Models\Traits;

use App\Models\SineNomine;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Copia el registro en 'sine_nomines' ANTES de borrarlo físicamente. No hay que
 * llamar a nada: se engancha al evento 'deleting' del modelo, así que la copia se
 * hace venga el borrado de donde venga (delete() sobre la instancia, destroy(), …).
 *
 * No usamos SoftDeletes porque la fila debe desaparecer: L9 comparte estas tablas
 * y seguiría viéndola.
 *
 * OJO: los eventos de Eloquent NO saltan en borrados masivos del query builder
 * (where(...)->delete()), en cascadas de FK ni en borrados hechos desde L9.
 *
 * El modelo puede desactivar el cifrado de la copia con:
 *   protected bool $cifrarCopiaAlBorrar = false;
 */
trait ConCopiaAlBorrar
{
    public static function bootConCopiaAlBorrar(): void
    {
        static::deleting(function ($modelo) {
            $modelo->copiarEnSineNomine();
        });
    }

    /** Copia y borrado, o ninguna de las dos: si algo falla, no queda copia huérfana. */
    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }

    /**
     * Guarda los atributos crudos del registro (los de la tabla: ni $hidden, ni
     * $casts, ni $appends) junto con su hash, para poder auditarlo o recuperarlo.
     */
    protected function copiarEnSineNomine(): void
    {
        $registro = json_encode($this->getAttributes());

        SineNomine::create([
            'user_id'       => auth()->id() ?? 0,
            'modelo'        => static::class,
            'registro'      => $this->cifraCopiaAlBorrar() ? Crypt::encryptString($registro) : $registro,
            'hash_registro' => hash('sha256', $registro),
        ]);
    }

    protected function cifraCopiaAlBorrar(): bool
    {
        return $this->cifrarCopiaAlBorrar ?? true;
    }
}
