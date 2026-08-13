<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Analiza un ZIP de copia de seguridad (Spatie: db-dumps/*.sql + storage/...) sin tocar
 * nada, y solo si se confirma, restaura por completo la base de datos y storage/ con ese
 * contenido: DROP de todas las tablas actuales + carga del .sql, y borrado/reposición del
 * mismo árbol de storage/ que usa `backup:run` para generarlo.
 *
 * A diferencia de doslago:db-reset, este comando SÍ está disponible en producción: es
 * precisamente la herramienta de recuperación para ese entorno.
 */
class BackupRestore extends Command
{
    protected $signature = 'doslago:db-restore';

    protected $description = 'Analiza y, si se confirma, restaura una copia de seguridad completa (BD + storage)';

    /**
     * Fichero de configuración local (está en .gitignore): se versiona restore.xml.example
     * como plantilla, igual que resetdatabase.xml/resetdatabase.xml.example.
     */
    const CONFIG = 'restore.xml';

    public function handle(): int
    {
        try {
            $directorio = $this->directorioBackups();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $ficheros = $this->listarZips($directorio);

        if ($ficheros === []) {
            $this->error("No hay ningún .zip en {$directorio}.");

            return 1;
        }

        $opciones = [];
        foreach ($ficheros as $ruta) {
            $opciones[$ruta] = basename($ruta).' ('.date('d/m/Y H:i', filemtime($ruta)).', '.$this->formatearBytes(filesize($ruta)).')';
        }

        $elegido = $this->choice('Selecciona el backup a analizar', array_values($opciones), 0);
        $rutaZip = array_search($elegido, $opciones, true);

        $password = $this->secret('Contraseña del ZIP (puede no ser la de este .env)');

        try {
            $informe = $this->analizarZip($rutaZip, $password);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->mostrarInforme($rutaZip, $informe);

        $this->newLine();
        $this->warn('Al importar se BORRA POR COMPLETO la base de datos actual (todas las tablas) y el árbol de storage/ que cubre el backup.');
        $this->warn('Se vacían las colas pendientes y se cierra la sesión de todos los usuarios (nada de otro momento sobrevive al restore).');
        $this->warn('Es IRREVERSIBLE. No hay confirmación adicional después de esta.');

        if (! $this->confirm('¿Continuar con la importación?', false)) {
            $this->info('Cancelado. No se ha tocado nada.');

            return 0;
        }

        try {
            $this->restaurar($rutaZip, $password, $informe);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info('Restauración completada.');

        return 0;
    }

    /**
     * Lee database/sql_procedures/restore.xml y devuelve el <directory> validado.
     */
    private function directorioBackups(): string
    {
        $ruta = database_path('sql_procedures/'.self::CONFIG);

        if (! file_exists($ruta)) {
            throw new RuntimeException('No existe database/sql_procedures/'.self::CONFIG.'. Copia restore.xml.example y ajusta <directory>.');
        }

        $xml = @simplexml_load_file($ruta);

        if ($xml === false) {
            throw new RuntimeException('No se pudo leer '.self::CONFIG.' (¿XML mal formado?).');
        }

        $directorio = trim((string) $xml->directory);

        if ($directorio === '') {
            throw new RuntimeException('El fichero '.self::CONFIG.' no tiene <directory>.');
        }

        if (! is_dir($directorio) || ! is_readable($directorio)) {
            throw new RuntimeException("El directorio configurado no existe o no se puede leer: {$directorio}");
        }

        return $directorio;
    }

    /**
     * @return array<int, string> Rutas absolutas de .zip, más reciente primero.
     */
    private function listarZips(string $directorio): array
    {
        $ficheros = glob(rtrim($directorio, '/').'/*.zip') ?: [];

        usort($ficheros, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $ficheros;
    }

    /**
     * Abre el ZIP y analiza su contenido SIN tocar la base de datos ni storage/:
     *  - lista de entradas storage/... (nombre y tamaño no van cifrados, no hace falta password)
     *  - conteo de registros por tabla del .sql (requiere password para descifrar solo esa entrada)
     *
     * @return array{db: array{motor: string, nombre: string}, tablas: array<string, int>, total_registros: int, storage: array{total_ficheros: int, total_bytes: int, por_carpeta: array<string, array{ficheros: int, bytes: int}>}, zip_total_bytes: int}
     */
    private function analizarZip(string $rutaZip, string $password): array
    {
        $zip = new ZipArchive();

        if ($zip->open($rutaZip) !== true) {
            throw new RuntimeException("No se pudo abrir el ZIP: {$rutaZip}");
        }

        try {
            $entradaSql = null;
            $motor = null;
            $nombreBd = null;

            $porCarpeta = [];
            $totalFicherosStorage = 0;
            $totalBytesStorage = 0;
            $zipTotalBytes = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $nombre = $stat['name'];
                $zipTotalBytes += $stat['size'];

                if ($entradaSql === null && preg_match('#^db-dumps/(\w+)-(.+)\.sql$#', $nombre, $m)) {
                    $entradaSql = $nombre;
                    $motor = $m[1];
                    $nombreBd = $m[2];

                    continue;
                }

                if (! str_starts_with($nombre, 'storage/') || str_ends_with($nombre, '/')) {
                    continue;
                }

                $resto = substr($nombre, strlen('storage/'));
                $segmentos = explode('/', $resto);
                $carpeta = count($segmentos) > 2
                    ? $segmentos[0].'/'.$segmentos[1]
                    : $segmentos[0];

                $porCarpeta[$carpeta] ??= ['ficheros' => 0, 'bytes' => 0];
                $porCarpeta[$carpeta]['ficheros']++;
                $porCarpeta[$carpeta]['bytes'] += $stat['size'];

                $totalFicherosStorage++;
                $totalBytesStorage += $stat['size'];
            }

            if ($entradaSql === null) {
                throw new RuntimeException('El ZIP no contiene ningún db-dumps/*.sql: no es un backup válido.');
            }

            $tmpSql = tempnam(sys_get_temp_dir(), 'dbrestore_');

            $zip->setPassword($password);

            $flujo = $zip->getStream($entradaSql);

            if ($flujo === false) {
                @unlink($tmpSql);

                throw new RuntimeException('Contraseña incorrecta o ZIP corrupto: no se pudo descifrar el .sql.');
            }

            $destino = fopen($tmpSql, 'wb');
            stream_copy_to_stream($flujo, $destino);
            fclose($destino);
            fclose($flujo);

            try {
                $tablas = $this->contarRegistrosPorTabla($tmpSql);
            } finally {
                @unlink($tmpSql);
            }

            return [
                'db' => ['motor' => $motor, 'nombre' => $nombreBd],
                'tablas' => $tablas,
                'total_registros' => array_sum($tablas),
                'storage' => [
                    'total_ficheros' => $totalFicherosStorage,
                    'total_bytes' => $totalBytesStorage,
                    'por_carpeta' => $porCarpeta,
                ],
                'zip_total_bytes' => $zipTotalBytes,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * Cuenta registros por tabla recorriendo el .sql EN STREAMING (nunca se carga entero en
     * memoria: puede pesar cientos de MB). mysqldump saca extended-insert: cada sentencia
     * `INSERT INTO `tabla` VALUES (...),(...),...;` va en una sola línea, y una tabla grande
     * puede repartirse en varias sentencias INSERT seguidas. Para contar tuplas por línea no
     * basta con contar "),(" a lo bruto: un valor de texto puede contener paréntesis
     * literales (una dirección "Calle Mayor (bajo)"), así que se recorre carácter a carácter
     * respetando el estado "dentro de comillas" para no confundir esos paréntesis con los
     * que delimitan cada tupla.
     *
     * @return array<string, int>
     */
    private function contarRegistrosPorTabla(string $rutaSql): array
    {
        $contadores = [];

        $fh = fopen($rutaSql, 'rb');

        try {
            while (($linea = fgets($fh)) !== false) {
                if (! str_starts_with($linea, 'INSERT INTO ')) {
                    continue;
                }

                if (! preg_match('/^INSERT INTO `([^`]+)` VALUES (.*);\s*$/s', $linea, $m)) {
                    continue;
                }

                $tabla = $m[1];
                $contadores[$tabla] ??= 0;
                $contadores[$tabla] += $this->contarTuplas($m[2]);
            }
        } finally {
            fclose($fh);
        }

        return $contadores;
    }

    /** Cuenta tuplas de nivel superior "(...)" de una lista VALUES, ignorando paréntesis dentro de cadenas. */
    private function contarTuplas(string $values): int
    {
        $profundidad = 0;
        $dentroCadena = false;
        $escape = false;
        $tuplas = 0;
        $longitud = strlen($values);

        for ($i = 0; $i < $longitud; $i++) {
            $c = $values[$i];

            if ($dentroCadena) {
                if ($escape) {
                    $escape = false;
                } elseif ($c === '\\') {
                    $escape = true;
                } elseif ($c === "'") {
                    $dentroCadena = false;
                }

                continue;
            }

            if ($c === "'") {
                $dentroCadena = true;
            } elseif ($c === '(') {
                $profundidad++;
            } elseif ($c === ')') {
                $profundidad--;

                if ($profundidad === 0) {
                    $tuplas++;
                }
            }
        }

        return $tuplas;
    }

    private function mostrarInforme(string $rutaZip, array $informe): void
    {
        $this->newLine();
        $this->info('=== Informe de '.basename($rutaZip).' ===');
        $this->line('Base de datos de origen: '.$informe['db']['motor'].' / '.$informe['db']['nombre']);
        $this->line('Tamaño total del ZIP (descomprimido): '.$this->formatearBytes($informe['zip_total_bytes']));

        $this->newLine();
        $this->line('Tablas: '.count($informe['tablas']).' — Registros totales: '.number_format($informe['total_registros'], 0, ',', '.'));

        $filas = [];
        ksort($informe['tablas']);
        foreach ($informe['tablas'] as $tabla => $n) {
            $filas[] = [$tabla, number_format($n, 0, ',', '.')];
        }
        $this->table(['Tabla', 'Registros'], $filas);

        $this->newLine();
        $this->line('storage/: '.$informe['storage']['total_ficheros'].' ficheros — '.$this->formatearBytes($informe['storage']['total_bytes']));

        $filasStorage = [];
        ksort($informe['storage']['por_carpeta']);
        foreach ($informe['storage']['por_carpeta'] as $carpeta => $datos) {
            $filasStorage[] = ['storage/'.$carpeta, $datos['ficheros'], $this->formatearBytes($datos['bytes'])];
        }
        $this->table(['Carpeta', 'Ficheros', 'Tamaño'], $filasStorage);
    }

    /**
     * DROP completo del esquema actual + carga del .sql + borrado/reposición de storage/.
     * Solo se llama tras la confirmación explícita del usuario.
     */
    private function restaurar(string $rutaZip, string $password, array $informe): void
    {
        $zip = new ZipArchive();

        if ($zip->open($rutaZip) !== true) {
            throw new RuntimeException("No se pudo reabrir el ZIP: {$rutaZip}");
        }

        $tmpSql = tempnam(sys_get_temp_dir(), 'dbrestore_');
        $cnf = null;

        try {
            $zip->setPassword($password);
            $entradaSql = 'db-dumps/'.$informe['db']['motor'].'-'.$informe['db']['nombre'].'.sql';
            $flujo = $zip->getStream($entradaSql);

            if ($flujo === false) {
                throw new RuntimeException('No se pudo descifrar el .sql al importar.');
            }

            $destino = fopen($tmpSql, 'wb');
            stream_copy_to_stream($flujo, $destino);
            fclose($destino);
            fclose($flujo);

            $this->info('Borrando el esquema actual...');
            $this->dropTodasLasTablas();

            $this->info('Cargando el .sql...');
            $cnf = $this->writeMysqlCnf();
            $this->cargarSql($cnf, $tmpSql);

            $this->info('Borrando y reponiendo storage/...');
            $this->restaurarStorage($zip);

            $this->info('Vaciando colas pendientes...');
            $this->vaciarColasPendientes();

            $this->info('Cerrando la sesión de todos los usuarios...');
            $this->forzarLogoutGlobal();
        } finally {
            $zip->close();
            @unlink($tmpSql);

            if ($cnf !== null) {
                @unlink($cnf);
            }
        }
    }

    private function dropTodasLasTablas(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $tablas = DB::select('SHOW TABLES');
            $columna = 'Tables_in_'.config('database.connections.mysql.database');

            foreach ($tablas as $fila) {
                $nombre = $fila->$columna;
                DB::statement('DROP TABLE `'.str_replace('`', '``', $nombre).'`');
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * La tabla `jobs` es parte normal del esquema y viaja en el .sql como cualquier otra: si
     * al hacerse el backup había algo en cola (typicamente el propio App\Jobs\BackupJob, que
     * sigue reservado en `jobs` mientras `backup:run` está en marcha dentro de él, porque solo
     * se borra al terminar), esa fila vuelve con un `reserved_at` antiguo. Cualquier
     * `queue:work` activo la ve como abandonada (pasado el retry_after) y la reejecuta —
     * con el `backup:run` eso significa un backup nuevo disparado solo por haber restaurado.
     * Todo lo que estuviera en cola en el momento del backup es, por definición, trabajo de
     * otro momento: no debe sobrevivir a un restore.
     */
    private function vaciarColasPendientes(): void
    {
        if (Schema::hasTable('jobs')) {
            DB::table('jobs')->truncate();
        }
    }

    /**
     * Ni la sesión (fichero o cookie firmada) ni el remember_token viajan en el backup, así
     * que sin este paso cualquiera que ya tuviera sesión iniciada (o cookie "recuérdame")
     * antes de restaurar se queda dentro después, contra datos que pueden ser de otro
     * origen/otro momento. Invalida las tres vías de sesión que soporta la app:
     *  - sessions en BD (si el driver es 'database': la tabla ya viene recién cargada del
     *    backup, con sesiones de esa fecha, no de ahora).
     *  - sessions en fichero (driver 'file'): no vienen en el zip (storage/framework va
     *    excluido de raíz), pero pueden seguir siendo válidas si nadie las toca.
     *  - remember_token de cada usuario: si no se cambia, la cookie "recuérdame" que ya
     *    tenía el navegador antes de restaurar sigue validando contra el valor que trajo
     *    el backup.
     */
    private function forzarLogoutGlobal(): void
    {
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->truncate();
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->update(['remember_token' => null]);
        }

        $sesionesFichero = storage_path('framework/sessions');

        if (is_dir($sesionesFichero)) {
            foreach (glob($sesionesFichero.'/*') ?: [] as $fichero) {
                if (is_file($fichero)) {
                    @unlink($fichero);
                }
            }
        }
    }

    /** Fichero temporal de credenciales para el cliente mysql, 0600 (nunca la contraseña como argumento). */
    private function writeMysqlCnf(): string
    {
        $config = config('database.connections.mysql');
        $esc = fn (string $valor) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $valor).'"';

        $ruta = tempnam(sys_get_temp_dir(), 'dbrestore_cnf_');

        file_put_contents($ruta, "[client]\n"
            .'host='.$esc($config['host'])."\n"
            .'port='.$esc((string) $config['port'])."\n"
            .'user='.$esc($config['username'])."\n"
            .'password='.$esc($config['password'])."\n");
        chmod($ruta, 0600);

        return $ruta;
    }

    private function cargarSql(string $cnf, string $rutaSql): void
    {
        $db = config('database.connections.mysql.database');

        $proceso = Process::fromShellCommandline(
            'mysql --defaults-extra-file='.escapeshellarg($cnf).' '.escapeshellarg($db).' < '.escapeshellarg($rutaSql)
        );
        $proceso->setTimeout(1800);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            throw new RuntimeException('Falló la carga del .sql: '.$proceso->getErrorOutput());
        }
    }

    /**
     * Borra el árbol de storage_path() con el mismo alcance que backup:run realmente cubre,
     * y repone ahí las entradas storage/... del ZIP. "Lo que realmente cubre" no es solo
     * config('backup.backup.source.files.exclude'): Spatie, además, excluye por su cuenta
     * (sin que salga en ningún config) el propio directorio de destino de los backups y el
     * temporal (ver vendor spatie/laravel-backup BackupJob::directoriosUsedByBackupJob()) —
     * si no se replica aquí ese mismo cálculo, este comando borraría storage/app/backups.
     */
    private function restaurarStorage(ZipArchive $zip): void
    {
        $esExcluido = $this->calcularExclusion();

        $fallos = [];

        $this->borrarArbol(storage_path(), $esExcluido, $fallos);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);

            if (! str_starts_with($nombre, 'storage/') || str_ends_with($nombre, '/')) {
                continue;
            }

            $destino = base_path($nombre);

            if ($esExcluido($destino)) {
                continue;
            }

            if (! is_dir(dirname($destino))) {
                mkdir(dirname($destino), 0775, true);
            }

            if (! @$zip->extractTo(base_path(), [$nombre]) || ! is_readable($destino)) {
                $fallos[] = "no se pudo escribir {$nombre} (¿permisos?)";
            }
        }

        if ($fallos !== []) {
            throw new RuntimeException(
                'La base de datos SÍ se ha restaurado, pero storage/ ha quedado incompleto '
                .'('.count($fallos)." fichero(s)/carpeta(s) sin poder borrar o reponer, probablemente por permisos):\n"
                .implode("\n", array_slice($fallos, 0, 20))
                .(count($fallos) > 20 ? "\n... y ".(count($fallos) - 20).' más.' : '')
            );
        }
    }

    /**
     * Réplica de BackupJob::directoriosUsedByBackupJob() de spatie/laravel-backup: la carpeta
     * de destino de cada disco local configurado (storage_path del disco + nombre del backup)
     * y el directorio temporal, sumados a config('backup.backup.source.files.exclude').
     *
     * @return callable(string): bool
     */
    private function calcularExclusion(): callable
    {
        $excluir = array_map(
            fn (string $ruta) => rtrim($ruta, '/'),
            config('backup.backup.source.files.exclude', [])
        );

        foreach (config('backup.backup.destination.disks', []) as $diskName) {
            if (config("filesystems.disks.{$diskName}.driver") !== 'local') {
                continue;
            }

            $root = rtrim((string) config("filesystems.disks.{$diskName}.root"), '/');

            if ($root !== '') {
                $excluir[] = $root.'/'.config('backup.backup.name');
            }
        }

        $temporal = rtrim((string) config('backup.backup.temporary_directory'), '/');

        if ($temporal !== '') {
            $excluir[] = $temporal;
        }

        return function (string $rutaAbsoluta) use ($excluir): bool {
            foreach ($excluir as $ruta) {
                if ($rutaAbsoluta === $ruta || str_starts_with($rutaAbsoluta, $ruta.'/')) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * Borra el contenido de $directorio salvo lo que decida $esExcluido (ruta absoluta).
     * No se hace rmdir() de las carpetas, solo se vacían: si el backup no trae nada para
     * alguna, debe seguir existiendo vacía, no desaparecer. Los fallos de borrado (permisos)
     * se acumulan en $fallos en vez de ignorarse: que algo no se haya podido borrar tiene
     * que acabar en un error visible, no en silencio.
     *
     * @param  array<int, string>  $fallos
     */
    private function borrarArbol(string $directorio, callable $esExcluido, array &$fallos): void
    {
        if ($esExcluido($directorio) || ! is_dir($directorio)) {
            return;
        }

        if (! is_writable($directorio)) {
            $fallos[] = "sin permiso de escritura en {$directorio}";

            return;
        }

        foreach (scandir($directorio) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $ruta = $directorio.'/'.$item;

            if ($esExcluido($ruta)) {
                continue;
            }

            if (is_dir($ruta) && ! is_link($ruta)) {
                $this->borrarArbol($ruta, $esExcluido, $fallos);
            } elseif (! @unlink($ruta)) {
                $fallos[] = "no se pudo borrar {$ruta}";
            }
        }
    }

    private function formatearBytes(int $bytes): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $valor = $bytes;
        $i = 0;

        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return round($valor, 2).' '.$unidades[$i];
    }
}
