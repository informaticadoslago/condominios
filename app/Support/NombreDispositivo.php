<?php

namespace App\Support;

use Laravel\Jetstream\Agent;

/**
 * Nombre automático del dispositivo en el que se activan las notificaciones push, sacado
 * del user agent: "iPhone", "Android · Chrome", "Mac · Safari"… Es solo un punto de
 * partida para que el usuario distinga sus dispositivos; lo puede cambiar en su perfil.
 */
class NombreDispositivo
{
    /** Los nombres de sistema que da Agent que no se entienden a la primera. */
    const SISTEMAS = [
        'OS X'      => 'Mac',
        'AndroidOS' => 'Android',
    ];

    public static function desde(?string $userAgent): string
    {
        $agent = tap(new Agent(), fn ($a) => $a->setUserAgent($userAgent ?? ''));

        // En iPhone/iPad solo hay push desde el icono de la pantalla de inicio, y ahí el
        // navegador no se identifica (Agent dice "Mozilla"): basta con el aparato.
        foreach (['iPhone', 'iPad'] as $aparato) {
            if ($agent->is($aparato)) {
                return $aparato;
            }
        }

        $sistema   = $agent->platform() ? (self::SISTEMAS[$agent->platform()] ?? $agent->platform()) : null;
        $navegador = $agent->browser() === 'Mozilla' ? null : $agent->browser();

        return implode(' · ', array_filter([$sistema, $navegador])) ?: __('Navegador');
    }
}
