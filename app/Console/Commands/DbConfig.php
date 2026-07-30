<?php
namespace App\Console\Commands;

use function Laravel\Prompts\form;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DbConfig extends Command
{
    protected $signature   = 'xestionmusical:db-config';
    protected $description = 'Crea (o recrea) la base de datos y el usuario, y escribe la configuración en .env';

    /**
     * Nombre de la conexión temporal que usamos con las credenciales
     * privilegiadas para crear la base de datos y el usuario.
     */
    protected const PRIV_CONNECTION = 'db_install_privileged';

    public function handle(): int
    {
        $this->alert('Configuración de la base de datos · dosLago');

        // Paso 1.1: crear / recrear la base de datos y el usuario.
        $data = $this->prepareDatabase();
        if ($data === null) {
            $this->warn('Configuración detenida.');
            return Command::FAILURE;
        }

        // Se copia .env.example en .env y, a continuación, se sobrescriben las
        // líneas DB_* con los valores realmente usados para crear la base de
        // datos, de modo que la aplicación se conecte con ellas.
        if (is_file(base_path('.env.example'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
            $this->info('✅ .env generado a partir de .env.example.');
        }

        $this->writeEnvValues([
            'DB_HOST'     => $data['host'],
            'DB_PORT'     => $data['port'],
            'DB_DATABASE' => $data['database'],
            'DB_USERNAME' => $data['app_user'],
            'DB_PASSWORD' => $data['app_password'],
        ]);

        // Regeneramos la clave de la aplicación sobre el .env recién escrito.
        Artisan::call('key:generate');

        $this->newLine();
        $this->info('✅ Configuración escrita en .env y clave de aplicación regenerada.');

        return Command::SUCCESS;
    }

    /**
     * Crea (o recrea) la base de datos y el usuario de la aplicación usando una
     * cuenta privilegiada. Devuelve los datos usados, o null si se detiene.
     *
     * @return array<string, string>|null
     */
    protected function prepareDatabase(): ?array
    {
        $mysql = config('database.connections.mysql');

        // Valores por defecto leídos de .env.example (si existe); si no, vacíos.
        $defaults = $this->envExampleDefaults();

        $passwordHint = $defaults['app_password'] !== ''
            ? 'Pulsa Enter para usar la contraseña definida en .env.example'
            : '';

        $formResults = form()
            ->text('host', 'Servidor (host) de MySQL', $mysql['host'] ?? 'localhost', required: true)
            ->text('port', 'Puerto', (string) ($mysql['port'] ?? '3306'), required: true)
            ->text('database', 'Nombre de la base de datos', $defaults['database'], required: true, validate: fn ($v) => $this->validateIdentifier($v, 'base de datos'))
            ->text('app_user', 'Usuario de la aplicación', $defaults['app_user'], required: true, validate: fn ($v) => $this->validateIdentifier($v, 'usuario'))
            ->password('app_password', 'Contraseña del usuario de la aplicación', hint: $passwordHint)
            ->text('app_user_host', 'Ámbito: host desde el que se conectará la aplicación', '%', required: true)
            ->text('priv_user', 'Usuario privilegiado (root u otro con permisos)', 'root', required: true)
            ->password('priv_password', 'Contraseña del usuario privilegiado')
            ->submit();

        $data = [
            'host'          => $formResults[0],
            'port'          => $formResults[1],
            'database'      => $formResults[2],
            'app_user'      => $formResults[3],
            // Si se deja en blanco, se usa la contraseña de .env.example.
            'app_password'  => $formResults[4] !== '' ? $formResults[4] : $defaults['app_password'],
            'app_user_host' => $formResults[5],
            'priv_user'     => $formResults[6],
            'priv_password' => $formResults[7] ?? '',
        ];

        if ($data['app_password'] === '') {
            $this->error('Debes indicar una contraseña para el usuario de la aplicación.');
            return null;
        }

        // Conexión privilegiada SIN base de datos, para poder crearla.
        $this->configurePrivilegedConnection($data, $mysql);

        if (! $this->checkPrivilegedConnection()) {
            return null;
        }

        // Si la base de datos ya existe, ofrecemos borrar su contenido y los
        // usuarios que tengan acceso a ella antes de recrearlo todo.
        $reset       = false;
        $usersToDrop = [];

        if ($this->databaseExists($data['database'])) {
            $this->newLine();
            $this->warn("⚠ La base de datos `{$data['database']}` ya existe.");

            if ($this->confirm('¿Deseas borrar su contenido y los usuarios con acceso a ella?', false)) {
                $reset       = true;
                $usersToDrop = $this->usersWithAccess($data['database'], $data['priv_user']);
            } else {
                $this->warn('La base de datos ya existe y no se va a borrar.');
                return null;
            }
        }

        $this->newLine();
        $this->line('Se van a ejecutar las siguientes operaciones:');
        if ($reset) {
            foreach ($usersToDrop as $u) {
                $this->line("  • DROP USER '{$u->User}'@'{$u->Host}'");
            }
            $this->line("  • DROP DATABASE `{$data['database']}` (se borra todo el contenido)");
        }
        $this->line("  • CREATE DATABASE `{$data['database']}`");
        $this->line("  • CREATE USER '{$data['app_user']}'@'{$data['app_user_host']}'");
        $this->line("  • GRANT ALL ON `{$data['database']}`.* al usuario");
        $this->newLine();

        if (! $this->confirm('¿Continuar?', true)) {
            $this->warn('Operación cancelada.');
            return null;
        }

        try {
            if ($reset) {
                $this->dropUsers($usersToDrop);
                $this->dropDatabase($data['database']);
            }
            $this->createDatabase($data);
            $this->createUserAndGrant($data);
            DB::connection(self::PRIV_CONNECTION)->statement('FLUSH PRIVILEGES');
        } catch (\Throwable $e) {
            $this->error('❌ Error durante la creación: ' . $e->getMessage());
            return null;
        }

        $this->newLine();
        $this->info('✅ Base de datos y usuario creados correctamente.');
        $this->line("   Base de datos: {$data['database']}");
        $this->line("   Usuario:       {$data['app_user']}@{$data['app_user_host']}");

        return $data;
    }

    /**
     * Lee de .env.example los valores por defecto para la base de datos y las
     * credenciales del usuario de la aplicación. Si el fichero no existe,
     * devuelve los tres campos vacíos.
     *
     * @return array{database: string, app_user: string, app_password: string}
     */
    protected function envExampleDefaults(): array
    {
        $empty = ['database' => '', 'app_user' => '', 'app_password' => ''];

        $path = base_path('.env.example');
        if (! is_file($path)) {
            return $empty;
        }

        $map = [
            'DB_DATABASE' => 'database',
            'DB_USERNAME' => 'app_user',
            'DB_PASSWORD' => 'app_password',
        ];

        $result = $empty;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = ltrim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);

            if (isset($map[$key])) {
                $result[$map[$key]] = $this->cleanEnvValue($value);
            }
        }

        return $result;
    }

    /**
     * Sobrescribe (o añade) las claves indicadas en el fichero .env, dejando
     * el resto del contenido intacto.
     *
     * @param array<string, string> $values
     */
    protected function writeEnvValues(array $values): void
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            $this->warn('No existe .env: no se pudieron escribir los valores DB_*.');
            return;
        }

        $lines = explode("\n", file_get_contents($path));

        foreach ($values as $key => $value) {
            $encoded = $this->encodeEnvValue((string) $value);
            $found   = false;

            foreach ($lines as $i => $line) {
                if (str_starts_with(ltrim($line), $key . '=')) {
                    $lines[$i] = $key . '=' . $encoded;
                    $found     = true;
                    break;
                }
            }

            if (! $found) {
                $lines[] = $key . '=' . $encoded;
            }
        }

        file_put_contents($path, implode("\n", $lines));
    }

    /**
     * Codifica un valor para .env: lo entrecomilla si contiene espacios o
     * caracteres especiales.
     */
    protected function encodeEnvValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"\'=]/', $value)) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return $value;
    }

    /**
     * Limpia un valor de .env: elimina espacios y comillas envolventes.
     */
    protected function cleanEnvValue(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2
            && ($value[0] === '"' || $value[0] === "'")
            && $value[strlen($value) - 1] === $value[0]) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Valida que un identificador (nombre de BD o usuario) sea seguro para
     * interpolar en una sentencia DDL (no admite parámetros enlazados).
     */
    protected function validateIdentifier(string $value, string $tipo): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            return "El nombre de $tipo solo puede contener letras, números y guion bajo.";
        }
        return null;
    }

    /**
     * Registra una conexión MySQL temporal con las credenciales privilegiadas
     * y SIN seleccionar base de datos (para poder crearla).
     */
    protected function configurePrivilegedConnection(array $data, array $mysql): void
    {
        Config::set('database.connections.' . self::PRIV_CONNECTION, array_merge($mysql, [
            'host'     => $data['host'],
            'port'     => $data['port'],
            'database' => '',
            'username' => $data['priv_user'],
            'password' => $data['priv_password'],
        ]));

        DB::purge(self::PRIV_CONNECTION);
    }

    protected function checkPrivilegedConnection(): bool
    {
        $this->newLine();
        $this->info('Verificando conexión con el usuario privilegiado...');
        try {
            DB::connection(self::PRIV_CONNECTION)->select('SELECT 1');
            $this->info('✅ Conexión correcta.');
            return true;
        } catch (\Throwable $e) {
            $this->error('❌ No se pudo conectar con las credenciales privilegiadas.');
            $this->line('   → ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Comprueba si la base de datos ya existe.
     */
    protected function databaseExists(string $database): bool
    {
        $rows = DB::connection(self::PRIV_CONNECTION)->select(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$database]
        );

        return $rows !== [];
    }

    /**
     * Devuelve los usuarios que tienen privilegios concedidos sobre la base de
     * datos indicada (excluyendo el usuario privilegiado con el que operamos).
     *
     * @return array<int, object{User: string, Host: string}>
     */
    protected function usersWithAccess(string $database, string $privUser): array
    {
        try {
            // En mysql.db el nombre de la BD guarda el guion bajo escapado
            // (es un comodín), así que comprobamos ambas variantes.
            $rows = DB::connection(self::PRIV_CONNECTION)->select(
                'SELECT DISTINCT User, Host FROM mysql.db WHERE Db = ? OR Db = ?',
                [$database, str_replace('_', '\\_', $database)]
            );
        } catch (\Throwable $e) {
            $this->warn('No se pudo consultar los usuarios con acceso (¿faltan permisos sobre mysql.db?). Se omitirá su borrado.');
            $this->line('   → ' . $e->getMessage());

            return [];
        }

        // Nunca tocamos el usuario privilegiado con el que estamos operando.
        return array_values(array_filter($rows, fn ($r) => $r->User !== $privUser));
    }

    /**
     * @param array<int, object{User: string, Host: string}> $users
     */
    protected function dropUsers(array $users): void
    {
        $conn = DB::connection(self::PRIV_CONNECTION);

        foreach ($users as $u) {
            $this->info("Eliminando usuario '{$u->User}'@'{$u->Host}'...");
            $user = $this->quote($u->User);
            $host = $this->quote($u->Host);
            $conn->statement("DROP USER IF EXISTS {$user}@{$host}");
        }
    }

    protected function dropDatabase(string $database): void
    {
        $this->info("Eliminando base de datos `{$database}` y todo su contenido...");
        DB::connection(self::PRIV_CONNECTION)->statement("DROP DATABASE IF EXISTS `{$database}`");
    }

    protected function createDatabase(array $data): void
    {
        $charset   = config('database.connections.mysql.charset', 'utf8mb4');
        $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        $this->info("Creando base de datos `{$data['database']}`...");
        DB::connection(self::PRIV_CONNECTION)->statement(
            "CREATE DATABASE IF NOT EXISTS `{$data['database']}` CHARACTER SET {$charset} COLLATE {$collation}"
        );
    }

    protected function createUserAndGrant(array $data): void
    {
        $conn = DB::connection(self::PRIV_CONNECTION);
        $user = $data['app_user'];
        $host = $data['app_user_host'];
        $pass = $this->quote($data['app_password']);

        $this->info("Creando usuario '{$user}'@'{$host}'...");
        $conn->statement("CREATE USER IF NOT EXISTS '{$user}'@'{$host}' IDENTIFIED BY {$pass}");

        // Aseguramos la contraseña aunque el usuario ya existiera.
        $conn->statement("ALTER USER '{$user}'@'{$host}' IDENTIFIED BY {$pass}");

        $this->info("Concediendo privilegios sobre `{$data['database']}`...");
        $conn->statement("GRANT ALL PRIVILEGES ON `{$data['database']}`.* TO '{$user}'@'{$host}'");
    }

    /**
     * Escapa un valor literal de cadena para MySQL (contraseñas, etc.).
     */
    protected function quote(string $value): string
    {
        return DB::connection(self::PRIV_CONNECTION)->getPdo()->quote($value);
    }
}
