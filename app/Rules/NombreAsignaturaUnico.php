<?php

namespace App\Rules;

use App\Models\Asignatura;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El nombre de una asignatura no se repite dentro del mismo módulo de un horario, ni
 * entre las que no tienen módulo. Se comprueba antes de guardar en vez de esperar a que
 * salte el índice único de la base de datos (horario_id, modulo_id, nombre), que además
 * no ve los repetidos sin módulo: MySQL no compara los NULL en un índice único.
 *
 * La consulta compara con la intercalación de la columna, igual que el índice: sin
 * distinguir mayúsculas ni acentos.
 */
class NombreAsignaturaUnico implements ValidationRule
{
    /**
     * @param  int|string|null  $moduloId  vacío = asignatura sin módulo
     * @param  int|null  $ignorarId  la asignatura que se está modificando
     */
    public function __construct(
        private ?int $horarioId,
        private int|string|null $moduloId,
        private ?int $ignorarId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existe = Asignatura::where('horario_id', $this->horarioId)
            ->where('nombre', trim((string) $value))
            ->when(
                $this->moduloId,
                fn ($q) => $q->where('modulo_id', $this->moduloId),
                fn ($q) => $q->whereNull('modulo_id'),
            )
            ->when($this->ignorarId, fn ($q) => $q->whereKeyNot($this->ignorarId))
            ->exists();

        if ($existe) {
            $fail($this->moduloId
                ? __('Ya existe una asignatura con ese nombre en ese módulo.')
                : __('Ya existe una asignatura con ese nombre sin módulo.'));
        }
    }
}
