<?php
namespace App\Console\Commands;

use function Laravel\Prompts\text;
use App\Models\TipoDocumentoIdentificativo;
use App\Rules\Includes\ValidadorDocumentoId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class AppInstaller extends Command
{
    protected $signature   = 'doslago:install
        {--skip-db-config : Omite la pregunta de configuración de BD (uso interno al relanzarse)}';
    protected $description = 'Instalación via CLI';

    /**
     * Comprueba la conexión usando el .env actual
     */
    protected function checkDatabaseConnection(): bool
    {
        $connection = config('database.default');
        $config     = config("database.connections.$connection");

        if (! $config || ! isset($config['host'])) {
            $this->error("⚠️ No se pudo leer la configuración de la conexión '{$connection}' desde el .env.");
            return false;
        }

        $this->line('');
        $this->info("Verificando conexión con la base de datos...");
        $this->line("  Driver:  {$connection}");
        $this->line("  Host:    {$config['host']}");
        $this->line("  Puerto:  " . ($config['port'] ?? 'default'));
        $this->line("  DB:      {$config['database']}");
        $this->line('');

        try {
            DB::select('SELECT 1');
            $this->info('✅ Conexión a la base de datos correcta.');
            return true;
        } catch (\Throwable $e) {
            $this->error('❌ No se pudo conectar a la base de datos.');
            $this->line('   → ' . $e->getMessage());
            return false;
        }
    }

    public function runMigrationsWithSeeders(): bool
    {
        try {
            $this->info('Insertando valores iniciales...');
            $this->info('Regenerando tablas...');
            Artisan::call('migrate', ['--step' => true, '--force' => true]);
        } catch (\Throwable $e) {
            $this->error('Error durante migraciones o seeders: ' . $e->getMessage());
            return false;
        }

        $this->info('Valores iniciales insertados.');
        return true;
    }

    /**
     * Ejecuta un comando artisan en un PROCESO NUEVO. Es necesario para el
     * Paso 1 porque el .env se carga al arrancar el framework y no puede
     * recargarse a mitad de ejecución: solo un proceso nuevo ve el .env recién
     * escrito.
     */
    protected function runArtisan(array $arguments): bool
    {
        $process = new Process(array_merge([PHP_BINARY, base_path('artisan')], $arguments));
        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process->isSuccessful();
    }

    /**
     * Importa la base de datos desde un fichero .sql de esquema. Busca, en este
     * orden, database/schema/install.sql, mysql-schema.sql y mysql-schema.dump.
     * Devuelve true si importa (o si no hay fichero) y false si falla.
     */
    protected function importSqlSchema(): bool
    {
        if (! $this->confirm('¿Deseas importar la base de datos?', false)) {
            return true;
        }

        $file = text(
            label: 'Ruta del fichero .sql a importar',
            placeholder: 'p. ej. ~/backups/dump.sql',
            required: true,
            validate: fn ($v) => is_file($this->resolveSchemaPath($v)) ? null : 'No existe el fichero indicado.',
        );

        $file = $this->resolveSchemaPath($file);

        $client = $this->resolveDbClient();
        if ($client === null) {
            $this->error('No se encontró el cliente mariadb/mysql para importar el esquema.');
            return false;
        }

        $conn = config('database.default');
        $cfg  = config("database.connections.$conn");

        $this->line('');
        $this->info('Importando base de datos desde ' . $file . ' ...');

        // La contraseña va por MYSQL_PWD (no aparece en `ps`) y el fichero se
        // importa por redirección, en streaming: el cliente lo lee del disco,
        // no se carga en memoria de PHP (imprescindible con .sql muy grandes).
        //   mariadb -h host -P port -u usuario base < fichero.sql
        $command = sprintf(
            '%s -h%s -P%s -u%s %s < %s',
            $client,
            escapeshellarg((string) $cfg['host']),
            escapeshellarg((string) ($cfg['port'] ?? '3306')),
            escapeshellarg((string) $cfg['username']),
            escapeshellarg((string) $cfg['database']),
            escapeshellarg($file)
        );

        $process = Process::fromShellCommandline(
            $command,
            base_path(),
            ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')] + $_ENV
        );
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('❌ Error importando el esquema:');
            $this->line($process->getErrorOutput());
            return false;
        }

        $this->info('✅ Base de datos importada.');
        return true;
    }

    /**
     * Resuelve una ruta introducida por el usuario: si es relativa, la ancla a
     * la raíz del proyecto; si es absoluta, la deja tal cual.
     */
    protected function resolveSchemaPath(string $path): string
    {
        $path = trim($path);

        // Quita comillas envolventes si las hubiera.
        if (strlen($path) >= 2
            && ($path[0] === '"' || $path[0] === "'")
            && $path[strlen($path) - 1] === $path[0]) {
            $path = substr($path, 1, -1);
        }

        // Deshace el escapado de espacios estilo shell ("\ " -> " ").
        $path = str_replace('\\ ', ' ', $path);

        if ($path === '') {
            return $path;
        }

        // Expande ~ al home del usuario.
        if ($path === '~' || str_starts_with($path, '~/')) {
            $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? '');
            if ($home !== '') {
                $path = $home . substr($path, 1);
            }
        }

        // Si es relativa, la ancla a la raíz del proyecto.
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * Localiza el binario del cliente de línea de comandos (mariadb o mysql).
     */
    protected function resolveDbClient(): ?string
    {
        $candidates = array_filter([config('database.command.name'), 'mariadb', 'mysql']);

        foreach ($candidates as $bin) {
            $probe = new Process(['sh', '-c', 'command -v ' . escapeshellarg($bin)]);
            $probe->run();
            if ($probe->isSuccessful() && trim($probe->getOutput()) !== '') {
                return $bin;
            }
        }

        return null;
    }

    /**
     * Reclasifica el tipo de documento de las personas que quedaron con el tipo
     * centinela "ERRONEO" (id 1), deduciéndolo del formato de su
     * documento_identificativo (NIF/NIE/CIF). Los no reconocibles se dejan
     * como están para revisión manual. Es idempotente: solo toca las filas
     * cuyo tipo sigue siendo ERRONEO.
     */
    protected function saneaTipoDocumento(): void
    {
        $validador = new ValidadorDocumentoId();

        $erroneo        = 1; // tipo_documento_identificativos: id 1 = 'ERRONEO'
        $reclasificados = ['NIF' => 0, 'NIE' => 0, 'CIF' => 0];
        $sinReconocer   = 0;

        DB::table('personas')
            ->where('tipo_nif_id', $erroneo)
            ->whereNotNull('documento_identificativo')
            ->where('documento_identificativo', '<>', '')
            ->orderBy('id')
            ->chunkById(500, function ($personas) use ($validador, &$reclasificados, &$sinReconocer) {
                foreach ($personas as $persona) {
                    $doc = strtoupper(trim($persona->documento_identificativo));

                    if ($validador->isValidNIF($doc)) {
                        $tipo = TipoDocumentoIdentificativo::DOCUMENTO_NIF;
                        $reclasificados['NIF']++;
                    } elseif ($validador->isValidNIE($doc)) {
                        $tipo = TipoDocumentoIdentificativo::DOCUMENTO_NIE;
                        $reclasificados['NIE']++;
                    } elseif ($validador->isValidCIF($doc)) {
                        $tipo = TipoDocumentoIdentificativo::DOCUMENTO_CIF;
                        $reclasificados['CIF']++;
                    } else {
                        $sinReconocer++;
                        continue;
                    }

                    DB::table('personas')
                        ->where('id', $persona->id)
                        ->update(['tipo_nif_id' => $tipo]);
                }
            });

        $this->info(sprintf(
            '✅ Saneamiento completado: %d NIF, %d NIE, %d CIF reclasificados. %d sin reconocer (siguen como ERRONEO).',
            $reclasificados['NIF'],
            $reclasificados['NIE'],
            $reclasificados['CIF'],
            $sinReconocer
        ));
    }

    public function handle()
    {

        $ascii = <<<"ASCII"
__  __         _   _                                   _           _
\ \/ /___  ___| |_(_) _/_  _ __    _ __ ___  _   _ ___(_) ___ __ _| |
 \  // _ \/ __| __| |/ _ \| '_ \  | '_ ` _ \| | | / __| |/ __/ _` | |
 /  \  __/\__ \ |_| | (_) | | | | | | | | | | |_| \__ \ | (_| (_| | |
/_/\_\___||___/\__|_|\___/|_| |_| |_| |_| |_|\__,_|___/_|\___\__,_|_|
                    _           _
                 __| | ___  ___| |    __ _  __ _  ___
                / _` |/ _ \/ __| |   / _` |/ _` |/ _ \
               | (_| | (_) \__ \ |__| (_| | (_| | (_) |
                \__,_|\___/|___/_____\__,_|\__, |\___/
                                           |___/

ASCII;

        $this->line($ascii);

        $this->alert('Instalador de dosLago');

        // Paso 1: configuración de la base de datos.
        //
        // Se ejecuta en un comando/proceso aparte (doslago:db-config)
        // porque el .env se carga al arrancar el framework: si lo creásemos a
        // mitad de este proceso, el Paso 2 seguiría sin ver la nueva conexión.
        // Tras configurarla, nos relanzamos en un proceso nuevo que ya lee el
        // .env recién escrito.
        if (! $this->option('skip-db-config')
            && $this->confirm('¿Deseas cambiar la configuración de la base de datos?', false)) {

            if (! $this->runArtisan(['doslago:db-config'])) {
                $this->error('No se pudo configurar la base de datos. Instalación detenida.');
                return Command::FAILURE;
            }

            $this->newLine();
            $this->info('Continuando la instalación con la nueva configuración...');
            $this->newLine();

            return $this->runArtisan(['doslago:install', '--skip-db-config'])
                ? Command::SUCCESS
                : Command::FAILURE;
        }

        // Paso 2: preparar la base de datos.
        //
        // 2.a) Preguntar si se desea importar la base de datos desde un .sql y,
        // en caso afirmativo, pedir la ruta. Se hace después de generar el .env
        // (Paso 1) y antes de las migraciones.
        if (! $this->importSqlSchema()) {
            $this->error('Instalación detenida: no se pudo importar la base de datos.');
            return Command::FAILURE;
        }

        // 2.b) Comprobar la conexión actual del .env.
        if (! $this->checkDatabaseConnection()) {
            $this->error('La configuración actual del .env no permite conectar a la base de datos.');
            return Command::FAILURE;
        }

        // Paso 3: copia de logos a public
        if ($this->confirm('¿Deseas copiar los logos a public?', true)) {
            $files = [
                'dosLago.png',
                'logo-circulo.png',
                'logo-circulo-blanco.png',
            ];

            $sourceDir = storage_path('doslago/logo');
            $targetDir = public_path('storage/images/logo');

            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            foreach ($files as $file) {
                $source = $sourceDir . '/' . $file;
                $target = $targetDir . '/' . $file;

                if (! file_exists($source)) {
                    $this->error("Archivo {$file} no encontrado en storage.");
                    continue;
                }

                copy($source, $target);
                $this->info("Archivo {$file} copiado/sobrescrito en public.");
            }
        }

        // Paso 4: migraciones y seeders
        if ($this->confirm('¿Deseas ejecutar las migraciones?', true)) {
            $this->info('Ejecutando migraciones y seeders...');

            if (! $this->runMigrationsWithSeeders()) {
                $this->error('Error en migraciones o credenciales incorrectas.');
                return Command::FAILURE;
            }
        }

        // Paso 5: saneamiento del tipo de documento de las personas.
        //
        // Las personas importadas sin tipo asignado quedan con el tipo
        // centinela "ERRONEO" (id 1). Aquí se deduce el tipo real (NIF/NIE/CIF)
        // a partir del formato de su documento; los no reconocibles se dejan
        // igual para revisión manual. Va tras las migraciones (necesita las
        // tablas) y sobre los datos ya importados.
        if ($this->confirm('¿Deseas sanear el tipo de documento de las personas (ERRONEO → NIF/NIE/CIF)?', true)) {
            $this->info('Saneando tipos de documento...');
            $this->saneaTipoDocumento();
        }

        // Paso 6: usuario superadmin
        if ($this->confirm('¿Deseas inicializar usuario superadmin?', true)) {
            Artisan::call('db:seed', ['--force' => true, '--class' => 'CreateSuperUserSeeder']);
            $this->info('Usuario inicializados.');
        }

        $this->alert('✅ Instalación completada correctamente.');
        return Command::SUCCESS;
    }
}
