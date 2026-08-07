<?php

namespace App\Support;

/**
 * Las habilidades (abilities de Sanctum) que puede llevar un token de API.
 *
 * Van escritas dentro del propio token cuando se crea, no en el usuario: el rol dice
 * quién es hoy quien llama y se le puede quitar; la habilidad es del token y nació
 * con él.
 */
class HabilidadToken
{
    /** Hace falta para todo lo que no sea leer. Sin ella, el token solo consulta. */
    public const ESCRIBIR = 'contabilidad-escribir';

    /** La empresa contable para la que vale el token; solo una por token. */
    public static function empresa(int $empresaContableId): string
    {
        return 'empresa-contable:'.$empresaContableId;
    }
}
