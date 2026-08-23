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

    /** Claves sueltas que no encajan por prefijo pero van en una pestaña concreta. */
    private const PESTANA_EXCEPCIONES = [
        'SAVE_USER_LAST_SEEN' => 'seguimiento',
    ];

    /** Claves editables por pestaña. Se van añadiendo una a una según se decide. */
    private const EDITABLES = [
        'seguimiento' => [
            'TRACK_LOGIN', 'TRACK_NEW_USER_REGISTRATION', 'SAVE_USER_LAST_SEEN',
        ],
        'correo' => [
            'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD',
            'MAIL_ENCRYPTION', 'MAIL_FROM_NAME', 'MAIL_FROM_ADDRESS',
            'EMAIL_FIRMA', 'EMAIL_FACTURA_FIRMA', 'EMAIL_SANDBOX', 'EMAIL_SANDBOX_TO',
        ],
        'backup' => [
            'BACKUP_MAIL_TO_ADDRESS', 'BACKUP_MAIL_FROM_NAME', 'BACKUP_MAIL_FROM_ADDRESS',
            'BACKUP_ARCHIVE_PASSWORD', 'BACKUP_HOUR_ONE', 'BACKUP_HOUR_TWO', 'BACKUP_HOUR_CLEAN',
        ],
        'otros' => [
            'SERVER_TEST_COLOR', 'LOGO_TEXT', 'LOGO_ALT', 'LOGO_IMG',
            'DOCUMENTOS_ROOT', 'COMS_ROOT',
            'ENVIAR_EMAIL_AL_ENVIAR_REMESA', 'ENVIAR_EMAIL_TRANSFERENCIAS',
        ],
    ];

    /** Valores en edición de todas las pestañas (solo claves editables no secretas). Se cargan una
     *  sola vez al abrir para que cambiar de pestaña no descarte cambios sin guardar. */
    public array $form = [];

    /** Claves que se han vuelto editables en esta sesión al importarlas (no estaban en EDITABLES). */
    public array $importadas = [];

    /** Modal secundario para pegar varias líneas CLAVE=VALOR y aplicarlas de golpe al formulario. */
    public bool $importarAbierto = false;

    public string $importarTexto = '';

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

    /** Carga en $form las claves editables no secretas de todas las pestañas. */
    private function cargarFormulario(): void
    {
        $env = $this->leerEnv();
        $this->form = [];

        foreach (self::EDITABLES as $claves) {
            foreach ($claves as $clave) {
                if (! $this->esSecreto($clave)) {
                    $this->form[$clave] = $env[$clave] ?? '';
                }
            }
        }

        foreach ($this->importadas as $clave) {
            $this->form[$clave] = $env[$clave] ?? '';
        }
    }

    public function esEditable(string $clave): bool
    {
        return in_array($clave, self::EDITABLES[$this->tab] ?? [], true)
            || in_array($clave, $this->importadas, true);
    }

    public function abrirImportar(): void
    {
        $this->importarTexto = '';
        $this->importarAbierto = true;
    }

    /** Aplica al formulario (en memoria) las líneas CLAVE=VALOR pegadas. Ignora comentarios,
     *  líneas mal formadas y claves secretas. Las claves que no estuvieran ya en EDITABLES
     *  pasan a ser editables en esta sesión; si la clave no existe en el .env se añadirá al
     *  final del archivo al guardar. */
    public function importar(): void
    {
        $aplicadas = 0;
        $ignoradas = 0;

        foreach (preg_split('/\r\n|\r|\n/', $this->importarTexto) as $linea) {
            $linea = trim($linea);

            if ($linea === '' || Str::startsWith($linea, '#') || ! Str::contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim(trim($valor), '"\'');

            if (! preg_match('/^[A-Z0-9_]+$/', $clave) || $this->esSecreto($clave)) {
                $ignoradas++;

                continue;
            }

            $this->form[$clave] = $valor;

            if (! in_array($clave, $this->importadas, true)) {
                $this->importadas[] = $clave;
            }

            $aplicadas++;
        }

        $this->importarAbierto = false;
        $this->importarTexto = '';

        $this->dispatch('toast-success', [
            'title' => __(':aplicadas aplicadas, :ignoradas ignoradas', ['aplicadas' => $aplicadas, 'ignoradas' => $ignoradas]),
        ]);
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

        $env = $this->leerEnv();

        // Claves recién importadas que aún no existen en disco: se muestran ya en su pestaña.
        foreach ($this->importadas as $clave) {
            if (! array_key_exists($clave, $env)) {
                $env[$clave] = $this->form[$clave] ?? '';
            }
        }

        foreach ($env as $clave => $valor) {
            $grupos[$this->pestanaDe($clave)][$clave] = $this->esSecreto($clave) ? '••••••' : $valor;
        }

        // Las no editables primero, dejando las editables agrupadas al final de cada pestaña.
        foreach ($grupos as $pestana => &$vars) {
            $editables = self::EDITABLES[$pestana] ?? [];
            uksort($vars, fn ($a, $b) => in_array($a, $editables, true) <=> in_array($b, $editables, true));
        }

        return $grupos;
    }

    private function pestanaDe(string $clave): string
    {
        if (isset(self::PESTANA_EXCEPCIONES[$clave])) {
            return self::PESTANA_EXCEPCIONES[$clave];
        }

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

    /** Reescribe en el .env las claves indicadas. Las que no existan ya como línea CLAVE=...
     *  se añaden al final del archivo. */
    private function escribirEnv(array $cambios): void
    {
        $ruta = base_path('.env');
        $lineas = file($ruta, FILE_IGNORE_NEW_LINES);
        $pendientes = $cambios;

        foreach ($lineas as $i => $linea) {
            if (! Str::contains($linea, '=')) {
                continue;
            }

            $clave = trim(explode('=', $linea, 2)[0]);

            if (array_key_exists($clave, $pendientes)) {
                $lineas[$i] = $clave.'='.$this->formatearValorEnv($pendientes[$clave]);
                unset($pendientes[$clave]);
            }
        }

        foreach ($pendientes as $clave => $valor) {
            $lineas[] = $clave.'='.$this->formatearValorEnv($valor);
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
