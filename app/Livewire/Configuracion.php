<?php

namespace App\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de configuración (solo superadmin, permiso 'global-configuracion'). De momento
 * MUESTRA en solo lectura las variables del .env repartidas en pestañas por prefijo;
 * el qué hacer con cada grupo (editar, mover a BD…) se decide después. Los secretos se
 * enmascaran: esto se ve por web y no debe filtrar credenciales.
 */
class Configuracion extends Component
{
    public bool $show = false;

    /** Pestaña activa. */
    public string $tab = 'sistema';

    /** Etiqueta de cada pestaña (id => texto a traducir). */
    public array $pestanas = [
        'sistema'     => 'Sistema',
        'correo'      => 'Correo',
        'seguimiento' => 'Seguimiento',
        'backup'      => 'Backup',
        'otros'       => 'Otros',
    ];

    /** Prefijos del .env que caen en cada pestaña. Lo que no encaja va a 'otros'. */
    private const PREFIJOS = [
        'sistema'     => ['APP_', 'DB_', 'SESSION_', 'REDIS_'],
        'correo'      => ['MAIL_', 'EMAIL'],
        'seguimiento' => ['TRACK', 'TELEGRAM_'],
        'backup'      => ['BACKUP_'],
    ];

    #[On('abrir-configuracion')]
    public function abrir(): void
    {
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }

    /** Reparte cada variable del .env en su pestaña por prefijo, con secretos enmascarados. */
    private function variablesPorPestana(): array
    {
        $grupos = array_fill_keys(array_keys($this->pestanas), []);

        foreach ($this->leerEnv() as $clave => $valor) {
            $grupos[$this->pestanaDe($clave)][$clave] = $this->esSecreto($clave) ? '••••••' : $valor;
        }

        return $grupos;
    }

    private function pestanaDe(string $clave): string
    {
        foreach (self::PREFIJOS as $pestana => $prefijos) {
            if (Str::startsWith($clave, $prefijos)) {
                return $pestana;
            }
        }

        return 'otros';
    }

    private function esSecreto(string $clave): bool
    {
        return Str::contains($clave, ['PASSWORD', 'SECRET', 'TOKEN', 'KEY']);
    }

    /** Parseo sencillo del .env: KEY=VALUE, ignora comentarios y líneas en blanco. */
    private function leerEnv(): array
    {
        $ruta = base_path('.env');

        if (! is_readable($ruta)) {
            return [];
        }

        $vars = [];

        foreach (file($ruta, FILE_IGNORE_NEW_LINES) as $linea) {
            $linea = trim($linea);

            if ($linea === '' || Str::startsWith($linea, '#') || ! Str::contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);

            if (! preg_match('/^[A-Z0-9_]+$/', $clave)) {
                continue;
            }

            $vars[$clave] = trim(trim($valor), '"\'');
        }

        return $vars;
    }

    public function render()
    {
        return view('livewire.configuracion', [
            'grupos' => $this->variablesPorPestana(),
        ]);
    }
}
