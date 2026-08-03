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
| [`doslago:installresources`](#doslagoinstallresources) | Copia los logos a `public/` | Ninguno (solo copia ficheros) |
| [`condominios:fakeseed`](#condominiosfakeseed) | Genera datos ficticios de demo | Solo en modo debug |
| [`condominios:comunidad-exportar`](#condominioscomunidad-exportar) | Exporta una comunidad completa a `.zip` | Ninguno (solo lectura) |
| [`condominios:comunidad-borrar`](#condominioscomunidad-borrar) | Borra una comunidad y todos sus datos | **Irreversible** |

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

## `condominios:comunidad-exportar`

```bash
php artisan condominios:comunidad-exportar {id}
```

Exporta **todos** los datos de una comunidad (la que tenga ese `id`; el `id` se ve
en la columna "ID" del listado de Comunidades) a un `.zip` en `storage/app/coms`:

- `datos.xml`: todas las filas de BD que cuelgan de la comunidad (inmuebles,
  personas, propietarios, proveedores, cuentas bancarias, presupuestos,
  contabilidad, documentos...), una tabla por elemento, una fila por `<fila>`.
- `ficheros.json`: el contenido binario (en base64) de los documentos/facturas
  adjuntos a los proveedores de la comunidad, enlazado por `documento_id`.
- `indice.md`: explica el contenido del zip y el orden para reconstruirlo en otro
  sistema.

Los ficheros sueltos se generan en una carpeta temporal, se comprimen y se borran:
en `storage/app/coms` solo queda el `.zip` final.

Comando de solo lectura: no modifica nada en la base de datos ni en los discos de
documentos.

---

## `condominios:comunidad-borrar`

```bash
php artisan condominios:comunidad-borrar {id}
```

Borra una comunidad y **todos** sus datos relacionados: inmuebles, personas,
propietarios, proveedores, cuentas bancarias, presupuestos, contabilidad, documentos
y facturas adjuntas, el histórico de estados, el rol `comunidad-{id}` y, si nadie
más la usa, su propia `Persona` (CIF/razón social).

Pide confirmación antes de ejecutar, mostrando primero lo que se va a borrar.

⚠️ **Irreversible.** En este proyecto no hay soft deletes: el borrado es físico.
Solo queda constancia (cifrada) en `sine_nomines` de los documentos, contactos y
direcciones borrados — el resto de tablas no deja rastro. Antes de borrar una
comunidad con datos reales, considera exportarla primero con
`condominios:comunidad-exportar`.
