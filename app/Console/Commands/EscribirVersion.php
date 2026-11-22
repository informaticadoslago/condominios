<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Escribe en el .env la versión del código desplegado, que es lo que enseña «Acerca de…»:
 *  - LAST_GIT_TAG: la etiqueta del último commit, o vacía si ese commit no tiene etiqueta
 *    (con commits posteriores a una etiqueta, el código ya no es el de esa etiqueta).
 *  - LAST_GIT_DATE: la fecha de ese commit (AAAA-MM-DD).
 *
 * Se lanza al desplegar, después de actualizar el código y antes de config:cache. La web
 * nunca ejecuta git: solo lee estas dos variables.
 *
 * Con --no-git-exe no ejecuta git (en Plesk no está al alcance): lee de la carpeta .git el
 * commit instalado y la dirección del repositorio, y pregunta al servidor que lo aloja.
 * La etiqueta la da cualquier servidor de git; la fecha, solo GitHub y GitLab.
 *
 * Si no consigue saber la versión no falla: deja las dos variables vacías y «Acerca de…»
 * dice «Versión desconocida». Mejor eso que seguir enseñando la de un despliegue anterior.
 */
class EscribirVersion extends Command
{
    protected $signature = 'doslago:version
        {--no-git-exe : No ejecuta git: lee la carpeta .git y pregunta al servidor que aloja el repositorio}';

    protected $description = 'Escribe en el .env la etiqueta y la fecha del último commit, para «Acerca de…»';

    /** Comentario que precede a las variables cuando se añaden al final del .env. */
    const COMENTARIO = '# Versión del código desplegado, para «Acerca de…». La escribe doslago:version al desplegar.';

    /** Segundos de espera a cada consulta al servidor del repositorio. */
    const TIEMPO_MAXIMO = 15;

    /** Por qué no se ha podido saber la versión (o parte de ella), para el aviso. */
    protected string $motivo = '';

    public function handle()
    {
        [$etiqueta, $fecha] = $this->option('no-git-exe') ? $this->leerRepositorio() : $this->leerGit();

        // Una etiqueta con caracteres raros no se escribe: rompería la línea del .env.
        if ($etiqueta !== '' && ! preg_match('/^[\w.\-\/+]+$/', $etiqueta)) {
            $this->warn("La etiqueta «{$etiqueta}» tiene caracteres que no caben en el .env: se deja vacía.");
            $etiqueta = '';
        }

        if (! $this->escribirEnv(['LAST_GIT_TAG' => $etiqueta, 'LAST_GIT_DATE' => $fecha])) {
            return Command::FAILURE;
        }

        if ($etiqueta !== '') {
            $this->info("Versión {$etiqueta}" . ($fecha !== '' ? " ({$fecha})." : '.'));
        } elseif ($fecha !== '') {
            $this->info("El último commit no tiene etiqueta: versión del {$fecha}.");
        } else {
            $this->warn('No se ha podido saber la versión: queda como desconocida.');
        }

        if ($this->motivo !== '' && ($etiqueta === '' || $fecha === '')) {
            $this->warn("Motivo: {$this->motivo}");
        }

        if ($this->laravel->configurationIsCached()) {
            $this->warn('La configuración está en caché: hasta que se ejecute config:cache, la app no verá la versión nueva.');
        }

        return Command::SUCCESS;
    }

    /**
     * Con el programa git.
     *
     * @return array{0: string, 1: string} Etiqueta y fecha del último commit; las dos vacías
     *                                     si git no está al alcance.
     */
    protected function leerGit(): array
    {
        // Sin .git propia, git subiría por las carpetas de arriba y podría leer otro repositorio.
        if (! File::exists(base_path('.git'))) {
            $this->motivo = 'no hay carpeta .git en ' . base_path();

            return ['', ''];
        }

        $fecha = $this->git(['log', '-1', '--format=%cd', '--date=short']);

        if ($fecha === null || ! $this->esFecha($fecha)) {
            return ['', ''];
        }

        // Si el commit tiene varias etiquetas, la de versión más alta.
        $etiquetas = $this->git(['tag', '--points-at', 'HEAD', '--sort=-v:refname']) ?? '';

        return [trim(strtok($etiquetas, "\n") ?: ''), $fecha];
    }

    /** Salida de git, o null si no se ha podido ejecutar o ha fallado. */
    protected function git(array $argumentos): ?string
    {
        try {
            $resultado = Process::path(base_path())->run(['git', ...$argumentos]);
        } catch (\Throwable $e) {
            $this->motivo = $e->getMessage();

            return null;
        }

        if (! $resultado->successful()) {
            $this->motivo = trim($resultado->errorOutput()) ?: 'git ha terminado con el código ' . $resultado->exitCode();

            return null;
        }

        return trim($resultado->output());
    }

    /**
     * Sin el programa git: el commit sale de la carpeta .git y la etiqueta y la fecha, del
     * servidor que aloja el repositorio. Las etiquetas del proyecto son anotadas y en .git
     * no se sabe a qué commit apuntan sin descomprimir sus objetos; el servidor sí lo dice.
     *
     * @return array{0: string, 1: string} Etiqueta y fecha; vacía la que no se haya podido saber.
     */
    protected function leerRepositorio(): array
    {
        $carpeta = base_path('.git');

        if (! File::isDirectory($carpeta)) {
            $this->motivo = 'no hay carpeta .git en ' . base_path();

            return ['', ''];
        }

        $commit = $this->commitInstalado($carpeta);

        if ($commit === null) {
            $this->motivo = 'no se ha podido leer el commit instalado en .git';

            return ['', ''];
        }

        $remoto = $this->repositorioRemoto($carpeta);

        if ($remoto === null) {
            $this->motivo = 'en .git/config no hay un repositorio remoto con dirección que se pueda consultar';

            return ['', ''];
        }

        return [$this->etiquetaRemota($remoto, $commit), $this->fechaRemota($remoto, $commit)];
    }

    /** El commit al que apunta HEAD, leído de .git sin el programa git. */
    protected function commitInstalado(string $carpeta): ?string
    {
        $head = trim(File::get("{$carpeta}/HEAD"));

        // HEAD suelto (sin rama): es el propio commit.
        if (preg_match('/^[0-9a-f]{40}$/', $head)) {
            return $head;
        }

        if (! preg_match('#^ref: (refs/\S+)$#', $head, $ref)) {
            return null;
        }

        // La rama está en su propio fichero o, si git la ha empaquetado, en packed-refs.
        if (File::exists("{$carpeta}/{$ref[1]}")) {
            $commit = trim(File::get("{$carpeta}/{$ref[1]}"));

            return preg_match('/^[0-9a-f]{40}$/', $commit) ? $commit : null;
        }

        if (File::exists("{$carpeta}/packed-refs")
            && preg_match('/^([0-9a-f]{40}) ' . preg_quote($ref[1], '/') . '$/m', File::get("{$carpeta}/packed-refs"), $linea)) {
            return $linea[1];
        }

        return null;
    }

    /**
     * Dirección del repositorio remoto (origin o, si no lo hay, el primero) de .git/config.
     *
     * @return array{host: string, ruta: string, usuario: ?string, clave: ?string, base: string}|null
     *         base: https://host/ruta, sin usuario ni clave (no se enseña nunca con ellos).
     */
    protected function repositorioRemoto(string $carpeta): ?array
    {
        if (! File::exists("{$carpeta}/config")) {
            return null;
        }

        preg_match_all('/^\s*\[remote\s+"([^"]+)"\]\s*$(.*?)(?=^\s*\[|\z)/ms', File::get("{$carpeta}/config"), $secciones, PREG_SET_ORDER);

        $urls = [];
        foreach ($secciones as [, $nombre, $cuerpo]) {
            if (preg_match('/^\s*url\s*=\s*(.+?)\s*$/m', $cuerpo, $url)) {
                $urls[$nombre] = $url[1];
            }
        }

        $url = $urls['origin'] ?? (reset($urls) ?: null);

        if ($url === null) {
            return null;
        }

        $usuario = $clave = null;

        if (preg_match('#^[\w.\-]+@([^:/]+):(.+)$#', $url, $scp)) {
            // git@servidor:ruta (ssh): se pregunta por https, que vale si el repositorio es público.
            [$host, $ruta, $esquema] = [$scp[1], $scp[2], 'https'];
        } else {
            $partes = parse_url($url);

            if (! $partes || empty($partes['host']) || ! in_array($partes['scheme'] ?? '', ['https', 'http', 'ssh', 'git'])) {
                return null;
            }

            $host    = $partes['host'];
            $ruta    = $partes['path'] ?? '';
            $esquema = ($partes['scheme'] === 'http') ? 'http' : 'https';

            // Por https puede traer usuario y clave (un token, en un repositorio privado).
            if (in_array($partes['scheme'], ['https', 'http'])) {
                $host   .= isset($partes['port']) ? ':' . $partes['port'] : '';
                $usuario = isset($partes['user']) ? rawurldecode($partes['user']) : null;
                $clave   = isset($partes['pass']) ? rawurldecode($partes['pass']) : null;
            }
        }

        $ruta = preg_replace('/\.git$/', '', trim($ruta, '/'));

        if ($ruta === '') {
            return null;
        }

        return [
            'host'    => $host,
            'ruta'    => $ruta,
            'usuario' => $usuario,
            'clave'   => $clave,
            'base'    => "{$esquema}://{$host}/{$ruta}",
        ];
    }

    /**
     * La etiqueta del commit, según la lista de referencias que da cualquier servidor de
     * git (la que pide git al hacer fetch). De cada etiqueta anotada trae dos líneas: la
     * del objeto etiqueta y, acabada en ^{}, la del commit al que apunta.
     */
    protected function etiquetaRemota(array $remoto, string $commit): string
    {
        $cuerpo = $this->consultar($remoto, "{$remoto['base']}.git/info/refs", ['service' => 'git-upload-pack'])?->body();

        if ($cuerpo === null) {
            return '';
        }

        preg_match_all('#([0-9a-f]{40}) refs/tags/([^\s\x00^]+)(\^\{\})?#', $cuerpo, $lineas, PREG_SET_ORDER);

        $etiquetas = [];
        foreach ($lineas as $linea) {
            if (! empty($linea[3])) {
                $etiquetas[$linea[2]] = $linea[1];   // anotada: el commit al que apunta
            } else {
                $etiquetas[$linea[2]] ??= $linea[1]; // ligera: ya es el commit
            }
        }

        $delCommit = array_keys(array_filter($etiquetas, fn ($apunta) => $apunta === $commit));

        // Si el commit tiene varias etiquetas, la de versión más alta.
        usort($delCommit, fn ($a, $b) => strnatcasecmp($b, $a));

        return $delCommit[0] ?? '';
    }

    /** La fecha del commit, de la API de GitHub o de GitLab; vacía con otro servidor. */
    protected function fechaRemota(array $remoto, string $commit): string
    {
        if ($remoto['host'] === 'github.com') {
            $respuesta = $this->consultar($remoto, "https://api.github.com/repos/{$remoto['ruta']}/commits/{$commit}");
            $fecha     = $respuesta?->json('commit.committer.date');
        } else {
            // GitLab, en gitlab.com o en un servidor propio. Si no es GitLab, no contesta y no hay fecha.
            $proyecto  = rawurlencode($remoto['ruta']);
            $respuesta = $this->consultar($remoto, "https://{$remoto['host']}/api/v4/projects/{$proyecto}/repository/commits/{$commit}");
            $fecha     = $respuesta?->json('committed_date');
        }

        if (! is_string($fecha)) {
            return '';
        }

        try {
            return Carbon::parse($fecha)->setTimezone(config('app.timezone'))->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    /** GET al servidor del repositorio, o null si no contesta bien. */
    protected function consultar(array $remoto, string $url, array $parametros = []): ?Response
    {
        try {
            $respuesta = $this->cliente($remoto, $url)->get($url, $parametros);
        } catch (\Throwable $e) {
            $this->motivo = "{$remoto['host']}/{$remoto['ruta']}: no contesta ({$e->getMessage()})";

            return null;
        }

        if (! $respuesta->successful()) {
            $this->motivo = "{$remoto['host']}/{$remoto['ruta']}: el servidor responde {$respuesta->status()}";

            return null;
        }

        return $respuesta;
    }

    /**
     * Cliente HTTP con las credenciales que traiga la dirección del repositorio: la API de
     * GitLab las quiere en su propia cabecera; el resto, como usuario y clave.
     */
    protected function cliente(array $remoto, string $url): PendingRequest
    {
        $cliente = Http::timeout(self::TIEMPO_MAXIMO)->withUserAgent('doslago-version');

        if ($remoto['clave'] !== null) {
            $cliente = str_contains($url, '/api/v4/')
                ? $cliente->withHeaders(['PRIVATE-TOKEN' => $remoto['clave']])
                : $cliente->withBasicAuth((string) $remoto['usuario'], $remoto['clave']);
        }

        return $cliente;
    }

    protected function esFecha(string $fecha): bool
    {
        try {
            return Carbon::createFromFormat('!Y-m-d', $fecha)->format('Y-m-d') === $fecha;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Sustituye las líneas de esas variables en el .env, o las añade al final si no están.
     * El resto del fichero queda tal cual.
     */
    protected function escribirEnv(array $valores): bool
    {
        $ruta = $this->laravel->environmentFilePath();

        if (! File::exists($ruta)) {
            $this->error("No existe el fichero {$ruta}.");

            return false;
        }

        $contenido = File::get($ruta);
        $faltan    = [];

        foreach ($valores as $clave => $valor) {
            $linea  = "{$clave}={$valor}";
            $patron = '/^' . preg_quote($clave, '/') . '=.*$/m';

            if (preg_match($patron, $contenido)) {
                $contenido = preg_replace_callback($patron, fn () => $linea, $contenido);
            } else {
                $faltan[] = $linea;
            }
        }

        if ($faltan !== []) {
            $contenido = rtrim($contenido, "\n") . "\n\n" . self::COMENTARIO . "\n" . implode("\n", $faltan) . "\n";
        }

        try {
            File::put($ruta, $contenido, true);
        } catch (\Throwable $e) {
            $this->error("No se ha podido escribir {$ruta}: {$e->getMessage()}");

            return false;
        }

        return true;
    }
}
