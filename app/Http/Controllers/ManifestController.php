<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * El manifest de la web: lo que lee el iPhone al añadirla a la pantalla de inicio, y sin
 * lo que no recibe notificaciones push. Es una ruta y no un fichero de public/ para que
 * el nombre salga de APP_NAME y el icono del genérico que deja doslago:installresources:
 * nada de la marca escrito a mano. Es pública porque el navegador la pide sin sesión.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name'             => config('app.name'),
            'short_name'       => config('app.name'),
            'start_url'        => route('dashboard', absolute: false),
            'display'          => 'standalone',
            'background_color' => '#ffffff',
            'theme_color'      => '#ffffff',
            'icons'            => [
                ['src' => asset('images/logo/icono-app.png'), 'sizes' => '1024x1024', 'type' => 'image/png'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
