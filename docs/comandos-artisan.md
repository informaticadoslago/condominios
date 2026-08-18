# Comandos artisan

Manual de todos los comandos de consola propios del proyecto (`app/Console/Commands`).
Se ejecutan desde la raíz del proyecto:

```bash
php artisan <comando>
```

## Índice

| Comando | Qué hace | Riesgo |
|---|---|---|
| [`condominios:install`](#condominiosinstall) | Instalación completa desde cero (guiada) | Vacía la base de datos actual |
| [`doslago:db-reset`](#doslagodb-reset) | Recrea la BD de una "comunidad" y migra | Borra la BD de esa comunidad |
| [`doslago:db-restore`](#doslagodb-restore) | Analiza y restaura un backup completo (BD + storage) | **Irreversible** |
| [`doslago:installresources`](#doslagoinstallresources) | Copia los logos a `public/` | Ninguno (solo copia ficheros) |
| [`condominios:fakeseed`](#condominiosfakeseed) | Genera datos ficticios de demo | Solo en modo debug |
| [`condominios:comunidades-listar`](#condominioscomunidades-listar) | Lista comunidades con `id` y nombre, una por línea | Ninguno (solo lectura) |
| [`condominios:comunidad-exportar`](#condominioscomunidad-exportar) | Exporta una comunidad completa a `.zip` | Ninguno (solo lectura) |
| [`condominios:comunidad-borrar`](#condominioscomunidad-borrar) | Borra una comunidad y todos sus datos | **Irreversible** |
| [`condominios:contabilidades-listar`](#condominioscontabilidades-listar) | Lista empresas contables con `id` y razón social, una por línea | Ninguno (solo lectura) |
| [`condominios:contabilidad-exportar`](#condominioscontabilidad-exportar) | Exporta una empresa contable completa a `.zip` | Ninguno (solo lectura) |
| [`condominios:contabilidad-borrar`](#condominioscontabilidad-borrar) | Borra una empresa contable y sus libros | **Irreversible** |
| [`condominios:cuentas-contables-listar`](#condominioscuentas-contables-listar) | Lista cuentas contables con `id` y nombre, una por línea | Ninguno (solo lectura) |

---

## `condominios:install`

```bash
php artisan condominios:install [--skip-env-setup]
```

Instalador guiado de una instancia nueva, pensado para arrancar el proyecto desde
cero (primer despliegue o reinstalación completa):

1. Prepara el `.env`: si ya hay uno que conecta, **vacía la base de datos actual**
   (`Schema::dropAllTables()`); si no hay `.env` o no conecta, lo regenera a partir
   de `.env.example` (guardando el anterior como `.env.old`) y relanza el proceso
   para que cargue la configuración nueva.
2. Si las credenciales del `.env` no conectan, pide un usuario privilegiado
   (por defecto `root`) para crear la base de datos y el usuario de la aplicación.
3. Copia los logos a `public/` (pregunta antes).
4. Ejecuta `migrate --seed`.
4. Pregunta si crear el usuario superadmin (`db:seed --class=CreateSuperUserSeeder`).

`--skip-env-setup` es de uso interno: el propio comando se relanza con esta opción
tras regenerar el `.env`, para no volver a preguntar por él.

⚠️ **Vacía toda la base de datos si ya hay una conectada.** Pensado para instalar,
no para actualizar una instancia con datos reales.

---

## `doslago:db-reset`

```bash
php artisan doslago:db-reset
```

Pensado para desarrollo local con varios entornos ("comunidades") definidos en
`app/Console/Commands/resetdatabase.xml` (local, no versionado; plantilla en
`resetdatabase.xml.example`; cada entorno se declara con una etiqueta `<escuela>`,
nombre heredado del proyecto hermano del que viene este comando). Para la
comunidad elegida:

1. Recrea su base de datos (vacía) y su usuario.
2. Ejecuta el script de limpieza `database/sql_procedures/clean-mysql-xestion.sql`.
3. Sustituye el `.env` por el de esa comunidad (el actual se guarda como `.env.old`) y
   regenera `APP_KEY`.
4. Aplica las migraciones (`migrate --step --force`).
5. Pregunta si rellenar con `db:seed` o solo crear el superadmin.

Solo está disponible (no aparece en `php artisan list`) en modo debug, o si todavía
no existe ningún `.env` (primer arranque).

⚠️ **Borra por completo la base de datos de la comunidad elegida.** Comando de
desarrollo, oculto fuera de `APP_DEBUG=true`.

---

## `doslago:db-restore`

```bash
php artisan doslago:db-restore [--local]
```

Analiza un `.zip` de copia de seguridad (Spatie: `db-dumps/*.sql` + `storage/...`) sin
tocar nada y, solo si se confirma, restaura por completo la base de datos y `storage/`
con ese contenido. A diferencia de `doslago:db-reset`, SÍ está disponible en producción:
es la herramienta de recuperación para ese entorno.

1. Lista los `.zip` disponibles y pide elegir uno.
2. Descifra solo el `.sql` para mostrar un informe (tablas y registros, ficheros y
   tamaño de `storage/` por carpeta) sin tocar la BD ni el disco.
3. Si se confirma, hace `DROP` de todas las tablas actuales, carga el `.sql`, borra y
   repone el mismo árbol de `storage/` que cubre `backup:run`, vacía las colas
   pendientes y cierra la sesión de todos los usuarios.

Por defecto, de dónde salen los `.zip` y la contraseña para descifrarlos depende de
`database/sql_procedures/restore.xml` (local, no versionado; plantilla en
`restore.xml.example`): un `<directory>` con la ruta a la carpeta de backups, y la
contraseña se pregunta por consola en cada ejecución (puede no coincidir con la de
este `.env`, por ejemplo al restaurar un backup traído de otro servidor).

Con `--local`, en vez de `restore.xml`: lee los `.zip` directamente del propio
directorio de destino de `backup:run` (disco `backups`, ver `BACKUPS_ROOT` en
[variables de entorno](variables-entorno.md)) y usa `BACKUP_ARCHIVE_PASSWORD` del
`.env` sin preguntar. Pensado para restaurar sobre el mismo servidor que generó el
backup, sin tener que crear `restore.xml` ni teclear la contraseña.

⚠️ **Irreversible.** Borra por completo la base de datos actual y el árbol de
`storage/` que cubre el backup. No hay confirmación adicional tras la de "¿Continuar
con la importación?".

---

## `doslago:installresources`

```bash
php artisan doslago:installresources
```

Copia los logos de `resources/images` (versionados en el repo) a
`public/storage/images/logo` (en `.gitignore`, no se despliega). Sin esto, el
`welcome`, el login y el favicon salen con el logo roto tras un despliegue nuevo.

Solo copia ficheros: seguro de ejecutar en cualquier entorno, incluida producción.

---

## `condominios:fakeseed`

```bash
php artisan condominios:fakeseed
```

Genera datos ficticios de demostración: comunidades, propietarios, inmuebles y un
presupuesto, llamando en orden a `DemoComunidadSeeder`, `DemoInmuebleSeeder` y
`DemoPresupuestoSeeder` (ninguno está en `DatabaseSeeder`, son solo para poblar una
demo). Cada ejecución genera comunidades nuevas con nombre y CIF al azar.

Solo está disponible (oculto fuera de `APP_DEBUG=true`) en modo debug.

---

## `condominios:comunidades-listar`

```bash
php artisan condominios:comunidades-listar
```

Lista las comunidades almacenadas con el formato estable `id nombre`, una entidad por
línea, para reutilizarlo desde otras tareas de consola o scripts de integración.

Ejemplo de salida:

```text
12 Comunidad Los Pinos
25 Comunidad Las Palmeras
```

El comando también admite el alias `condominios:comunidad-listar`.

---

## `condominios:contabilidades-listar`

```bash
php artisan condominios:contabilidades-listar
```

Lista las empresas contables con el formato `id razon_social`, una empresa por línea,
para consumir en el resto de comandos u automatizaciones en lote.

Ejemplo de salida:

```text
1 Empresa Local S.L.
6 Sociedad de Gestión Contable
```

El comando también admite el alias `condominios:contabilidad-listar`.

---

## `condominios:cuentas-contables-listar`

```bash
php artisan condominios:cuentas-contables-listar [empresa]
```

Lista las cuentas contables con el formato `id nombre`, una por línea. Si se pasa un
entero como argumento, el filtro se restringe a la empresa contable indicada;
si no, sale el conjunto completo de cuentas cargadas en la base.

Ejemplo de salida:

```text
1001 Banco
1002 Tesorería
2500 Propietarios
```

---

## `condominios:comunidad-exportar`

```bash
php artisan condominios:comunidad-exportar {id}
```

Exporta **todos** los datos de una comunidad (la que tenga ese `id`; el `id` se ve
en la columna "ID" del listado de Comunidades) a un `.zip` en `storage/app/coms`:

- `datos.xml`: todas las filas de BD que cuelgan de la comunidad (inmuebles,
  personas, propietarios, proveedores, cuentas bancarias, mandatos SEPA,
  presupuestos, recibos, remesas, cobros, avisos, documentos...), una tabla por
  elemento, una fila por `<fila>`.
- `ficheros.json`: el contenido binario (en base64) de los documentos adjuntos a los
  proveedores de la comunidad y a sus mandatos SEPA, enlazado por `documento_id`.
- `indice.md`: explica el contenido del zip y el orden para reconstruirlo en otro
  sistema.

Los ficheros sueltos se generan en una carpeta temporal, se comprimen y se borran:
en `storage/app/coms` solo queda el `.zip` final.

Comando de solo lectura: no modifica nada en la base de datos ni en los discos de
documentos.

**No incluye la contabilidad**: es un módulo independiente, sin claves ajenas hacia
comunidades, y se exporta aparte con
[`condominios:contabilidad-exportar`](#condominioscontabilidad-exportar). Para llevarse
una comunidad entera con sus libros hacen falta los dos `.zip`.

> Cada vez que aparezca una tabla nueva colgando de la comunidad hay que añadirla a
> `ComunidadExportador` **y** a `tests/Feature/Comunidades/ComunidadExportarTest.php`:
> si se olvida, el `.zip` sale incompleto sin dar ningún error.

---

## `condominios:comunidad-borrar`

```bash
php artisan condominios:comunidad-borrar {id}
```

Borra una comunidad y **todos** sus datos relacionados: inmuebles, personas,
propietarios, proveedores, cuentas bancarias, presupuestos, documentos
y facturas adjuntas, el histórico de estados, el rol `comunidad-{id}` y, si nadie
más la usa, su propia `Persona` (CIF/razón social). La contabilidad no se toca: va
por su lado, con [`condominios:contabilidad-borrar`](#condominioscontabilidad-borrar).

Pide confirmación antes de ejecutar, mostrando primero lo que se va a borrar.

⚠️ **Irreversible.** En este proyecto no hay soft deletes: el borrado es físico.
Solo queda constancia (cifrada) en `sine_nomines` de los documentos, contactos y
direcciones borrados — el resto de tablas no deja rastro. Antes de borrar una
comunidad con datos reales, considera exportarla primero con
`condominios:comunidad-exportar`.

⚠️ **Pendiente**: este comando aún no borra `recibos`, `remesas`, `lineas_remesas`,
`cobros`, `avisos_recibos` ni `mandatos_sepa`. Todas tienen clave ajena `RESTRICT`
hacia presupuestos, inmuebles, propietarios, cuentas bancarias y la propia comunidad,
así que en una comunidad que ya haya emitido recibos falla a mitad del borrado. La
exportación sí está al día.

---

## `condominios:contabilidad-exportar`

```bash
php artisan condominios:contabilidad-exportar {id}
```

El equivalente de `comunidad-exportar` para el otro módulo: exporta una **empresa
contable** entera a un `.zip` en `storage/app/coms`:

- `datos.xml`: la fila de `empresas_contables` y todo lo suyo — plan de cuentas
  (`cuenta_contables`, ordenadas por código para poder reconstruir la jerarquía de
  arriba abajo), `tercero_contables`, `ejercicio_contables`, `asiento_contables` y
  `apunte_contables`, con los importes en céntimos tal cual se guardan.
- `indice.md`: el contenido, los ejercicios, un aviso si algún asiento no cuadra y el
  orden para reconstruirlo en otro sistema.

No hay `ficheros.json`: la contabilidad no guarda adjuntos.

Lo que **no** viaja: los catálogos globales (`tipo_cuenta_contables`,
`tipo_tercero_contables`, `tipo_ingreso_contables`, `estados`), el plan de cuentas
maestro (las filas de `cuenta_contables` con `empresa_contable_id` nulo) y el rol y los
tokens de API de la empresa, que son credenciales.

Las columnas `sujeto_tipo`/`sujeto_id` y `referencia_tipo`/`referencia_id` salen tal
cual: para la contabilidad son texto opaco y no se traducen.

Comando de solo lectura.

---

## `condominios:contabilidad-borrar`

```bash
php artisan condominios:contabilidad-borrar {id}
```

Borra una empresa contable y **todos** sus libros: apuntes, asientos, terceros, plan de
cuentas (de las hojas hacia la raíz, por la jerarquía de `cuenta_padre_id`), ejercicios,
el rol `empresa-contable-{id}` y los tokens de API que solo valían para esa empresa.
Todo dentro de una transacción: o cae entero o no cae nada.

Antes de pedir confirmación avisa de qué comunidades llevaban sus libros ahí. Si se
confirma, a esas comunidades se les deja `empresa_contable_id` a nulo — sus datos de
gestión (presupuestos, recibos, facturas) no se tocan, solo pierden el enlace contable.

⚠️ **Irreversible.** Antes de borrar una empresa con datos reales, expórtala con
`condominios:contabilidad-exportar`.
