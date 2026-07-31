<?php
namespace App\Console\Commands;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class AppInstaller extends Command
{
    protected $signature   = 'condominios:install
        {--skip-env-setup : Omite la preparación del .env (uso interno al relanzarse)}';
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
            DB::purge($connection);
            DB::select('SELECT 1');
            $this->info('✅ Conexión a la base de datos correcta.');
            return true;
        } catch (\Throwable $e) {
            $this->error('❌ No se pudo conectar a la base de datos.');
            $this->line('   → ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Paso 1: prepara el .env y la base de datos.
     *
     * - Si ya existe un .env que conecta: vacía la base de datos y continúa
     *   en este mismo proceso.
     * - Si no existe .env, o el que hay no conecta: lo regenera a partir de
     *   .env.example (haciendo antes una copia de seguridad a .env.old si
     *   procede) y regenera la APP_KEY. Devuelve false para indicar que hace
     *   falta relanzar el proceso, ya que el nuevo .env no se puede recargar
     *   a mitad de ejecución.
     *
     * @return bool|null true = seguir en este proceso, false = hay que
     *                    relanzar, null = error irrecuperable (ya impreso).
     */
    protected function prepareEnvironment(): ?bool
    {
        $envPath = base_path('.env');

        if (file_exists($envPath) && $this->checkDatabaseConnection()) {
            $this->info('Vaciando la base de datos existente...');
            Schema::dropAllTables();
            $this->info('✅ Base de datos vaciada.');
            return true;
        }

        $examplePath = base_path('.env.example');

        if (! file_exists($examplePath)) {
            $this->error('❌ No tengo acceso a la base de datos. Instalación detenida.');
            return null;
        }

        if (file_exists($envPath)) {
            rename($envPath, base_path('.env.old'));
            $this->info('.env renombrado a .env.old.');
        }

        copy($examplePath, $envPath);
        $this->info('.env creado a partir de .env.example.');

        Artisan::call('key:generate', ['--force' => true]);
        $this->info('APP_KEY regenerada.');

        return false;
    }

    /**
     * Paso 1 (continuación): comprueba que se puede conectar con las
     * credenciales del .env. Si no se puede, pide un usuario con privilegios
     * (por defecto root) para crear la base de datos, el usuario de la app y
     * concederle privilegios, y vuelve a comprobar la conexión.
     */
    protected function ensureDatabaseReady(): bool
    {
        if ($this->checkDatabaseConnection()) {
            return true;
        }

        $this->warn('No se pudo conectar con el usuario de la aplicación. Hace falta un usuario con privilegios para crearlo.');

        $adminUser = text(label: 'Usuario de administración de la base de datos', default: 'root', required: true);
        $adminPass = password(label: 'Contraseña de administración de la base de datos');

        if (! $this->createAppDatabaseAndUser($adminUser, $adminPass)) {
            return false;
        }

        return $this->checkDatabaseConnection();
    }

    /**
     * Crea (si no existen) la base de datos y el usuario de la aplicación
     * definidos en el .env, usando una conexión aparte con credenciales de
     * administración. No toca la configuración de conexión de la app.
     */
    protected function createAppDatabaseAndUser(string $adminUser, #[\SensitiveParameter] string $adminPass): bool
    {
        $conn = config('database.default');
        $cfg  = config("database.connections.$conn");

        $database = $cfg['database'];
        $appUser  = $cfg['username'];
        $appPass  = $cfg['password'];
        $host     = '%';

        try {
            $pdo = new \PDO(
                sprintf('mysql:host=%s;port=%s', $cfg['host'], $cfg['port'] ?? 3306),
                $adminUser,
                $adminPass
            );
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $safeDatabase = str_replace('`', '``', $database);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $stmt = $pdo->prepare('CREATE USER IF NOT EXISTS ?@? IDENTIFIED BY ?');
            $stmt->execute([$appUser, $host, $appPass]);

            $pdo->exec(sprintf(
                "GRANT ALL PRIVILEGES ON `{$safeDatabase}`.* TO %s@%s",
                $pdo->quote($appUser),
                $pdo->quote($host)
            ));
            $pdo->exec('FLUSH PRIVILEGES');
        } catch (\Throwable $e) {
            $this->error('❌ No se pudo crear la base de datos/usuario: ' . $e->getMessage());
            return false;
        }

        $this->info("✅ Base de datos «{$database}» y usuario «{$appUser}» preparados.");
        return true;
    }

    public function runMigrationsWithSeeders(): bool
    {
        try {
            $this->info('Ejecutando migraciones y seeders...');
            Artisan::call('migrate', ['--step' => true, '--force' => true, '--seed' => true], $this->output);
        } catch (\Throwable $e) {
            $this->error('Error durante migraciones o seeders: ' . $e->getMessage());
            return false;
        }

        $this->info('✅ Migraciones y seeders completados.');
        return true;
    }

    /**
     * Ejecuta un comando artisan en un PROCESO NUEVO. Es necesario tras
     * escribir un .env nuevo porque el .env se carga al arrancar el
     * framework y no puede recargarse a mitad de ejecución: solo un proceso
     * nuevo ve el .env recién escrito.
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
     * Copia los logos de la app (en resources/images) a public, para que
     * las vistas que los sirven vía asset('storage/images/logo/...') los
     * encuentren.
     */
    protected function copyLogos(): void
    {
        $files = [
            'dosLago.png',
            'logo-circulo.png',
            'logo-circulo-blanco.png',
        ];

        $sourceDir = resource_path('images');
        $targetDir = public_path('storage/images/logo');

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach ($files as $file) {
            $source = $sourceDir . '/' . $file;
            $target = $targetDir . '/' . $file;

            if (! file_exists($source)) {
                $this->error("Archivo {$file} no encontrado en resources/images.");
                continue;
            }

            copy($source, $target);
            $this->info("Archivo {$file} copiado/sobrescrito en public.");
        }
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

        // Paso 1: preparar el .env y la base de datos.
        if (! $this->option('skip-env-setup')) {
            $status = $this->prepareEnvironment();

            if ($status === null) {
                return Command::FAILURE;
            }

            if ($status === false) {
                $this->newLine();
                $this->info('Continuando la instalación con la nueva configuración...');
                $this->newLine();

                return $this->runArtisan(['condominios:install', '--skip-env-setup'])
                    ? Command::SUCCESS
                    : Command::FAILURE;
            }
        }

        if (! $this->ensureDatabaseReady()) {
            $this->error('Instalación detenida: no se pudo preparar el acceso a la base de datos.');
            return Command::FAILURE;
        }

        // Copia de logos a public.
        if ($this->confirm('¿Deseas copiar los logos a public?', true)) {
            $this->copyLogos();
        }

        // Paso 2: migraciones y seeders.
        if (! $this->runMigrationsWithSeeders()) {
            return Command::FAILURE;
        }

        // Usuario superadmin (aparte, no está en DatabaseSeeder).
        if ($this->confirm('¿Deseas inicializar usuario superadmin?', true)) {
            Artisan::call('db:seed', ['--force' => true, '--class' => 'CreateSuperUserSeeder']);
            $this->info('Usuario inicializados.');
        }

        $this->alert('✅ Instalación completada correctamente.');
        return Command::SUCCESS;
    }
}
