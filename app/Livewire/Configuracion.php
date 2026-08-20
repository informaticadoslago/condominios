<?php

namespace App\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de configuración (solo superadmin, permiso 'global-configuracion'). Muestra las
 * variables del .env repartidas en pestañas por prefijo; solo las claves listadas en
 * EDITABLES se pueden modificar, una a una según se va decidiendo. Los secretos se
 * enmascaran y se editan aparte, en un modal de solo escritura (nunca se muestra el valor
 * actual): esto se ve por web y no debe filtrar credenciales.
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

    /** Claves editables por pestaña. Se van añadiendo una a una según se decide. */
    private const EDITABLES = [
        'correo' => [
            'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD',
            'MAIL_ENCRYPTION', 'MAIL_FROM_NAME', 'MAIL_FROM_ADDRESS',
            'EMAIL_FIRMA', 'EMAIL_FACTURA_FIRMA', 'EMAIL_SANDBOX', 'EMAIL_SANDBOX_TO',
        ],
    ];

    /** Valores en edición de la pestaña activa (solo claves editables no secretas). */
    public array $form = [];

    /** Modal secundario para cambiar una clave secreta (p.ej. MAIL_PASSWORD). */
    public bool $passwordAbierto = false;

    public string $passwordClave = '';

    public string $passwordNueva = '';

    public string $passwordConfirmacion = '';

    #[On('abrir-configuracion')]
    public function abrir(): void
    {
        $this->show = true;
        $this->cargarFormulario();
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function updatedTab(): void
    {
        $this->cargarFormulario();
    }

    /** Carga en $form las claves editables no secretas de la pestaña activa. */
    private function cargarFormulario(): void
    {
        $env = $this->leerEnv();
        $this->form = [];

        foreach (self::EDITABLES[$this->tab] ?? [] as $clave) {
            if (! $this->esSecreto($clave)) {
                $this->form[$clave] = $env[$clave] ?? '';
            }
        }
    }

    public function esEditable(string $clave): bool
    {
        return in_array($clave, self::EDITABLES[$this->tab] ?? [], true);
    }

    /** Guarda en el .env los valores editados de la pestaña activa. */
    public function guardar(): void
    {
        $this->escribirEnv($this->form);
        $this->cargarFormulario();

        $this->dispatch('toast-success', ['title' => __('Configuración guardada')]);
    }

    public function abrirPassword(string $clave): void
    {
        $this->passwordClave = $clave;
        $this->passwordNueva = '';
        $this->passwordConfirmacion = '';
        $this->passwordAbierto = true;
    }

    public function guardarPassword(): void
    {
        if ($this->passwordNueva !== $this->passwordConfirmacion) {
            $this->dispatch('toast-error', ['title' => __('Las contraseñas no coinciden')]);

            return;
        }

        $this->escribirEnv([$this->passwordClave => $this->passwordNueva]);
        $this->passwordAbierto = false;

        $this->dispatch('toast-success', ['title' => __('Contraseña actualizada')]);
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

    public function esSecreto(string $clave): bool
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

    /** Reescribe en el .env las claves indicadas (deben existir ya como línea CLAVE=...). */
    private function escribirEnv(array $cambios): void
    {
        $ruta = base_path('.env');
        $lineas = file($ruta, FILE_IGNORE_NEW_LINES);

        foreach ($lineas as $i => $linea) {
            if (! Str::contains($linea, '=')) {
                continue;
            }

            $clave = trim(explode('=', $linea, 2)[0]);

            if (array_key_exists($clave, $cambios)) {
                $lineas[$i] = $clave.'='.$this->formatearValorEnv($cambios[$clave]);
            }
        }

        file_put_contents($ruta, implode(PHP_EOL, $lineas).PHP_EOL);
    }

    /** Envuelve entre comillas dobles si hay espacios o caracteres fuera de [A-Za-z0-9_.-/:@]. */
    private function formatearValorEnv(string $valor): string
    {
        if ($valor === '' || preg_match('/^[A-Za-z0-9_.\-\/:@]+$/', $valor)) {
            return $valor;
        }

        return '"'.str_replace('"', '\"', $valor).'"';
    }

    public function render()
    {
        return view('livewire.configuracion', [
            'grupos' => $this->variablesPorPestana(),
        ]);
    }
}
