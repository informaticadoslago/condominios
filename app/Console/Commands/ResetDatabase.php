<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Deja la base de datos y el .env de una escuela listos: recrea la base de datos (vacía)
 * según el .env de la escuela, sustituye el .env y aplica encima las migraciones de L12.
 * No importa ningún volcado ni toca storage/app: eso queda fuera de este comando.
 *
 * El .env se copia ANTES de migrar, porque las migraciones leen de él los ids de
 * 'tipos_de_tipos' de la escuela (ver config/defines.php: tipos_l9).
 *
 * El env_file de cada escuela viaja por git, así que no debe llevar secretos reales: si la
 * escuela tiene createdatabase=1 (resetdatabase.xml), DB_DATABASE/DB_USERNAME/DB_PASSWORD
 * del .env resultante salen de ahí (local, en .gitignore) y no del env_file; y el APP_KEY
 * siempre se regenera (nunca el del env_file, que sería el mismo para todas las escuelas).
 */
class ResetDatabase extends Command
{
    protected $signature = 'doslago:db-reset';

    protected $description = 'Recrea la BD de una escuela a partir de su .env y aplica las migraciones de L12';

    /**
     * Fichero de configuración de las escuelas, junto a este comando. Es local (está en
     * .gitignore): se versiona resetdatabase.xml.example como plantilla, igual que el .env.
     */
    const CONFIG = 'resetdatabase.xml';

    public function __construct()
    {
        parent::__construct();

        // Borra bases de datos: fuera de un entorno de desarrollo no se enseña siquiera.
        if (! $this->entornoPermitido()) {
            $this->hidden = true;
        }
    }

    public function handle()
    {
        if (! $this->entornoPermitido()) {
            $this->error('Este comando solo está disponible en modo debug.');

            return 1;
        }

        try {
            $escuelas = $this->escuelas();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $escuela = $this->choice('Selecciona una opción', array_keys($escuelas), 0);
        $config  = $escuelas[$escuela];

        $envFile = $config['ENV_FILE'];

        // Comprobado ANTES de tocar nada: a mitad de camino, con la base ya borrada, un
        // .env que falta deja el entorno inservible.
        if (! file_exists(base_path($envFile))) {
            $this->error("No existe el fichero {$envFile}.");

            return 1;
        }

        if ($config['CREATE_DATABASE']) {
            // El nombre/usuario/contraseña de la BD ya no salen del envFile (viaja por git):
            // salen de resetdatabase.xml (local) y esa misma cuenta hace de admin y de app.
            $dbUser = $config['CREATE_ACCESS_NAME'] !== '' ? $config['CREATE_ACCESS_NAME'] : $this->ask('Usuario de MySQL de la aplicación');
            $dbPass = $config['CREATE_ACCESS_PASSWORD'] !== '' ? $config['CREATE_ACCESS_PASSWORD'] : $this->secret("Contraseña de {$dbUser}");
            $dbName = $config['CREATE_ACCESS_DATABASE'] !== '' ? $config['CREATE_ACCESS_DATABASE'] : $this->ask('Nombre de la base de datos');

            if (empty($dbName) || empty($dbUser)) {
                $this->error('Faltan datos de la base de datos (usuario o nombre).');

                return 1;
            }

            $adminUser = $dbUser;
            $adminPass = $dbPass;
        } else {
            $dbName = $this->envValue($envFile, 'DB_DATABASE');
            $dbUser = $this->envValue($envFile, 'DB_USERNAME');
            $dbPass = $this->envValue($envFile, 'DB_PASSWORD') ?? '';

            if (empty($dbName) || empty($dbUser)) {
                $this->error("No se pudieron leer DB_DATABASE/DB_USERNAME de {$envFile}");

                return 1;
            }

            // Cuenta con permisos para crear/borrar la BD. Según la escuela (resetdatabase.xml):
            //  - createaccess=1: basta el propio usuario del .env, ya tiene permisos.
            //  - createaccessname/createaccesspassword: se usa esa cuenta.
            //  - si no hay nada: se piden por pantalla.
            if ($config['CREATE_ACCESS']) {
                $adminUser = $dbUser;
                $adminPass = $dbPass;
            } elseif ($config['CREATE_ACCESS_NAME'] !== '') {
                $adminUser = $config['CREATE_ACCESS_NAME'];
                $adminPass = $config['CREATE_ACCESS_PASSWORD'];
            } else {
                $adminUser = $this->ask('Usuario privilegiado de MySQL', 'root');
                $adminPass = $this->secret("Contraseña de {$adminUser}");
            }
        }

        $this->warn("Se va a BORRAR y recrear la base de datos '{$dbName}' (escuela {$escuela}).");
        $this->warn("Se va a sustituir el .env por {$envFile} (el actual se guarda como .env.old).");

        if ($config['CREATE_DATABASE']) {
            $this->warn("En ese .env, DB_DATABASE/DB_USERNAME/DB_PASSWORD se sustituyen por '{$dbName}'/'{$dbUser}'/(la contraseña dada) y se genera un APP_KEY nuevo.");
        }

        if (! $this->confirm('¿Continuar?', true)) {
            $this->info('Cancelado.');

            return 0;
        }

        // Se pregunta ya (antes de tocar la base de datos) y se guarda la respuesta: el resto
        // del proceso no vuelve a ser interactivo, y las migraciones tardan lo suyo.
        $seedTodo = $this->confirm('¿Quieres rellenar las tablas con valores iniciales (db:seed) al finalizar?', true);
        $seedSuperadmin = $seedTodo ? false : $this->confirm('¿Deseas crear usuario superadmin?', true);

        try {
            $this->info("Recreando base de datos '{$dbName}' y usuario '{$dbUser}'...");
            $adminCnf = $this->provisionar($adminUser, $adminPass, $dbName, $dbUser, $dbPass);

            $this->info('Ejecutando script de limpieza...');
            $this->execOrFail($this->mysql($adminCnf, $dbName) . ' < ' . escapeshellarg(database_path('sql_procedures/clean-mysql-xestion.sql')));

            $this->info('Actualizando ficheros .env...');
            $this->cambiarEnv($envFile);

            if ($config['CREATE_DATABASE']) {
                $this->establecerEnvValor('.env', 'DB_DATABASE', $dbName);
                $this->establecerEnvValor('.env', 'DB_USERNAME', $dbUser);
                $this->establecerEnvValor('.env', 'DB_PASSWORD', $dbPass);
            }

            // La key del envFile viaja por git y es la misma para todas las escuelas: no
            // protege nada compartida así (cifra sesiones/cookies). Cada reset se lleva la
            // suya, generada aquí, nunca la del repositorio.
            $this->info('Generando APP_KEY...');
            $this->artisan(['key:generate', '--force'], $this->variablesDe('.env'));

            // En proceso aparte (este ya arrancó con el .env viejo) y PASÁNDOLE las variables
            // del .env YA DEFINITIVO (tras los pasos de arriba, no las del envFile plantilla,
            // que a estas alturas están obsoletas): no basta con lanzarlo fuera. Laravel mete
            // las variables del .env en el entorno real (putenv), el hijo las hereda, y dotenv
            // NO pisa una variable de entorno que ya existe: sin esto el hijo migraría la
            // escuela ANTERIOR y, si ya estaba migrada, diría "Nothing to migrate" y saldría
            // con 0. Sin un solo error.
            $this->info('Ejecutando migraciones de L12...');
            $variables = $this->variablesDe('.env');
            $this->artisan(['config:clear'], $variables);
            $this->artisan(['migrate', '--step', '--force'], $variables);

            if ($seedTodo) {
                $this->info('Rellenando valores iniciales (db:seed)...');
                $this->artisan(['db:seed', '--force'], $variables);
            } elseif ($seedSuperadmin) {
                $this->info('Creando usuario superadmin...');
                $this->artisan(['db:seed', '--force', '--class=CreateSuperUserSeeder'], $variables);
            }

            $this->info("Proceso finalizado correctamente para {$escuela} (BD: {$dbName})");

            return 0;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        } finally {
            if (isset($adminCnf) && file_exists($adminCnf)) {
                @unlink($adminCnf);
            }
        }
    }

    /**
     * Modo debug, o si todavía no hay .env (primer arranque: no hay entorno real que
     * proteger, y config('app.debug') sale false a falta de APP_DEBUG que leer).
     */
    private function entornoPermitido(): bool
    {
        return config('app.debug') || ! file_exists(base_path('.env'));
    }

    /**
     * Las escuelas se leen de resetdatabase.xml (raíz del proyecto, junto a los
     * .env.<escuela>), no de una constante: así se añade o cambia una escuela sin tocar el
     * comando. Devuelve el array indexado por el id de cada <escuela>:
     * [ id => ['ENV_FILE' => ..., 'CREATE_ACCESS' => ..., ...] ].
     */
    private function escuelas(): array
    {
        $ruta = __DIR__ . '/' . self::CONFIG;

        if (! file_exists($ruta)) {
            throw new RuntimeException('No existe el fichero de configuración ' . self::CONFIG . '.');
        }

        $xml = @simplexml_load_file($ruta);

        if ($xml === false) {
            throw new RuntimeException('No se pudo leer ' . self::CONFIG . ' (¿XML mal formado?).');
        }

        $escuelas = [];

        foreach ($xml->escuela as $escuela) {
            $id = trim((string) $escuela['id']);

            if ($id === '') {
                throw new RuntimeException('Hay una <escuela> sin atributo id en ' . self::CONFIG . '.');
            }

            $escuelas[$id] = [
                'ENV_FILE'               => trim((string) $escuela->env_file),
                // Cuenta con permisos para crear/borrar la BD (evita pedir la de root):
                //  createaccess=1 usa el propio usuario del .env; si no, la cuenta
                //  createaccessname/createaccesspassword; y si no hay nada, se pide.
                'CREATE_ACCESS'          => trim((string) $escuela['createaccess']) === '1',
                'CREATE_ACCESS_NAME'     => trim((string) $escuela->createaccessname),
                'CREATE_ACCESS_PASSWORD' => (string) $escuela->createaccesspassword,
                // createdatabase=1: el nombre/usuario/contraseña de la BD del .env resultante
                // NO salen del envFile (que viaja por git), sino de esta misma cuenta
                // (createaccessname/createaccesspassword) y de createaccessdatabase; lo que
                // falte se pide por pantalla. Esa cuenta sirve de admin y de conexión de la app.
                'CREATE_DATABASE'        => trim((string) $escuela['createdatabase']) === '1',
                'CREATE_ACCESS_DATABASE' => trim((string) $escuela->createaccessdatabase),
            ];
        }

        if ($escuelas === []) {
            throw new RuntimeException('No hay ninguna <escuela> definida en ' . self::CONFIG . '.');
        }

        return $escuelas;
    }

    /**
     * Sin usar por ahora: handle() ya no gestiona backup ni storage, solo BD y .env. Se deja
     * sin borrar por si se retoma más adelante.
     */
    private function reemplazarStorage(string $origen): void
    {
        $destino = storage_path('app');

        if (is_dir($destino)) {
            $this->execOrFail('rm -rf ' . escapeshellarg($destino));
        }

        $this->execOrFail('cp -a ' . escapeshellarg($origen) . ' ' . escapeshellarg($destino));

        // El storage de L9 no trae los .gitignore de Laravel y el rm se los lleva por
        // delante: sin esto, cada db-reset los deja borrados en el repositorio.
        $this->execOrFail('git -C ' . escapeshellarg(base_path()) . ' checkout -- storage/app 2>/dev/null || true');
    }

    /** El .env que se va, se guarda como .env.old (solo se conserva el último). */
    private function cambiarEnv(string $envFile): void
    {
        if (file_exists(base_path('.env.old'))) {
            unlink(base_path('.env.old'));
        }

        if (file_exists(base_path('.env'))) {
            rename(base_path('.env'), base_path('.env.old'));
        }

        copy(base_path($envFile), base_path('.env'));
    }

    /**
     * Sustituye el valor de una variable ya presente en un .env (o la añade si no está):
     * para DB_DATABASE/DB_USERNAME/DB_PASSWORD, que en CREATE_DATABASE ya no vienen del
     * envFile versionado sino de resetdatabase.xml.
     */
    private function establecerEnvValor(string $file, string $clave, string $valor): void
    {
        $ruta = base_path($file);
        $contenido = file_get_contents($ruta);
        $linea = "{$clave}={$valor}";

        $nuevo = preg_replace('/^' . preg_quote($clave, '/') . '=.*$/m', $linea, $contenido, 1, $sustituciones);

        file_put_contents($ruta, $sustituciones > 0 ? $nuevo : rtrim($contenido) . "\n{$linea}\n");
    }

    private function mysql(string $rootCnf, ?string $db = null): string
    {
        return 'mysql --defaults-extra-file=' . escapeshellarg($rootCnf) . ($db ? ' ' . escapeshellarg($db) : '');
    }

    /** Un valor del .env, sin cargarlo en la aplicación. */
    private function envValue(string $file, string $key): ?string
    {
        return $this->variablesDe($file)[$key] ?? null;
    }

    /**
     * Todas las variables de un .env, para dárselas al proceso hijo: son las que tienen
     * que ganar a las que hereda del entorno de este proceso.
     */
    private function variablesDe(string $file): array
    {
        $variables = [];

        foreach (file(base_path($file), FILE_IGNORE_NEW_LINES) as $linea) {
            $linea = trim($linea);

            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }

            if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $linea, $encontrado)) {
                continue;
            }

            $valor = trim($encontrado[2]);

            if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
                $valor = substr($valor, 1, -1);
            } elseif (str_contains($valor, ' #')) {
                // Comentario al final de una línea sin comillas (TIPO_CUOTA_MATRICULA=299 #...).
                $valor = trim(strstr($valor, ' #', true));
            }

            $variables[$encontrado[1]] = $valor;
        }

        return $variables;
    }

    /** Lanza un artisan en proceso aparte con las variables del .env de la escuela. */
    private function artisan(array $argumentos, array $variables): void
    {
        $proceso = new Process([PHP_BINARY, base_path('artisan'), ...$argumentos], base_path(), $variables);
        $proceso->setTimeout(null);
        $proceso->run(fn ($tipo, $salida) => $this->output->write($salida));

        if (! $proceso->isSuccessful()) {
            throw new RuntimeException('Falló: php artisan ' . implode(' ', $argumentos));
        }
    }

    /**
     * Aprovisiona la BD (DROP/CREATE DATABASE + usuario) con la cuenta admin dada. Si el
     * servidor responde "Access denied" (esa cuenta no tiene privilegios), pide por
     * pantalla una cuenta privilegiada -por defecto root- y reintenta con ella, tantas
     * veces como haga falta. Devuelve el fichero de credenciales (--defaults-extra-file)
     * que ha funcionado, para seguir usándolo en el resto de pasos (y borrarlo al terminar).
     */
    private function provisionar(string $adminUser, string $adminPass, string $dbName, string $dbUser, string $dbPass): string
    {
        while (true) {
            $adminCnf = $this->writeRootCnf($adminUser, $adminPass);
            $sql      = $this->provisionSql($dbName, $dbUser, $dbPass, $adminUser !== $dbUser);

            [$codigo, $error] = $this->ejecutarSql($adminCnf, $sql);

            if ($codigo === 0) {
                return $adminCnf;
            }

            @unlink($adminCnf);

            if (! str_contains($error, 'Access denied')) {
                throw new RuntimeException("Error al ejecutar (código {$codigo}). Se detiene la ejecución.");
            }

            $this->warn("Acceso denegado para el usuario '{$adminUser}'.");

            $adminUser = $this->ask('Usuario privilegiado de MySQL', 'root');
            $adminPass = $this->secret("Contraseña de {$adminUser}");
        }
    }

    /**
     * El SQL de aprovisionamiento en sí (DROP/CREATE DATABASE y, si hace falta, el
     * usuario). Se ejecuta desde fichero (no pasa por la shell): identificadores con
     * backticks y literales entre comillas simples, ambos escapados.
     *
     * $gestionarUsuario es false cuando el admin y el usuario de la app son la MISMA
     * cuenta (createaccess=1, o CREATE_DATABASE con createaccessname reutilizado): para
     * llegar aquí ya nos hemos autenticado con ese usuario y contraseña, así que su
     * existencia está probada y CREATE USER sobra (y en hosting compartido, ese
     * privilegio ni lo tiene: solo el de su propia base).
     */
    private function provisionSql(string $db, string $user, string $pass, bool $gestionarUsuario = true): string
    {
        $qId  = fn (string $valor) => '`' . str_replace('`', '``', $valor) . '`';
        $qStr = fn (string $valor) => "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $valor) . "'";

        $sentencias = [
            "DROP DATABASE IF EXISTS {$qId($db)};",
            "CREATE DATABASE {$qId($db)} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
        ];

        if ($gestionarUsuario) {
            $sentencias[] = "CREATE USER IF NOT EXISTS {$qStr($user)}@'%' IDENTIFIED BY {$qStr($pass)};";
            $sentencias[] = "ALTER USER {$qStr($user)}@'%' IDENTIFIED BY {$qStr($pass)};";
            $sentencias[] = "GRANT ALL PRIVILEGES ON {$qId($db)}.* TO {$qStr($user)}@'%';";
            $sentencias[] = 'FLUSH PRIVILEGES;';
        }

        return implode("\n", $sentencias) . "\n";
    }

    /** Fichero temporal de credenciales para mysql, 0600. */
    private function writeRootCnf(string $user, string $password): string
    {
        $esc = fn (string $valor) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $valor) . '"';

        $ruta = tempnam(sys_get_temp_dir(), 'dbreset_');

        file_put_contents($ruta, "[client]\n" . 'user=' . $esc($user) . "\n" . 'password=' . $esc($password) . "\n");
        chmod($ruta, 0600);

        return $ruta;
    }

    /**
     * Ejecuta una cadena SQL (fichero temporal + redirección de stdin) devolviendo
     * [código, stderr] en vez de lanzar: lo usa provisionar() para distinguir un "Access
     * denied" (pide otra cuenta y reintenta) de cualquier otro fallo (aborta).
     */
    private function ejecutarSql(string $rootCnf, string $sql): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dbsql_');
        file_put_contents($tmp, $sql);

        try {
            $proceso = Process::fromShellCommandline($this->mysql($rootCnf) . ' < ' . escapeshellarg($tmp));
            $proceso->run(fn ($tipo, $salida) => $this->output->write($salida));

            return [$proceso->getExitCode(), $proceso->getErrorOutput()];
        } finally {
            @unlink($tmp);
        }
    }

    private function execOrFail(string $comando): void
    {
        system($comando, $salida);

        if ($salida !== 0) {
            throw new RuntimeException("Error al ejecutar (código {$salida}). Se detiene la ejecución.");
        }
    }
}
