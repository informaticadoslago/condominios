<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccesoDirecto extends Model
{
    protected $table = 'accesos_directos';

    protected $fillable = ['user_id', 'nombre', 'url', 'icono', 'orden'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Aplana el menú (config/sidebar) en entradas navegables: [ '/url' => ['url','nombre','icono'] ].
     * Solo entradas con destino real (route resoluble o href que sea un path).
     */
    public static function entradasMenu(): array
    {
        $entradas = [];

        $recorrer = function ($items) use (&$recorrer, &$entradas) {
            foreach ($items as $item) {
                if (($item['type'] ?? 'item') === 'group') {
                    $recorrer($item['items'] ?? []);
                    continue;
                }

                $url = null;
                if (isset($item['route'])) {
                    try {
                        $url = route($item['route'], [], false);
                    } catch (\Throwable $e) {
                        $url = null; // rutas con parámetro obligatorio: se resuelven vía href
                    }
                } elseif (isset($item['href']) && str_starts_with($item['href'], '/')) {
                    $url = $item['href'];
                }

                if ($url) {
                    $url            = '/' . ltrim($url, '/');
                    $entradas[$url] = [
                        'url'    => $url,
                        'nombre' => __($item['label'] ?? ''),
                        'icono'  => $item['icon'] ?? null,
                    ];
                }
            }
        };

        foreach (config('sidebar.content', []) as $bloque) {
            if (($bloque['type'] ?? '') === 'nav') {
                $recorrer($bloque['items'] ?? []);
            }
        }

        return $entradas;
    }

    public static function entradaMenuPara(string $path): ?array
    {
        return self::entradasMenu()['/' . ltrim($path, '/')] ?? null;
    }
}
