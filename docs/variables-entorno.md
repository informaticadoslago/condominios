# Variables de entorno (.env)

Manual de las variables de `.env.example`. Solo se documentan las que son propias
de este proyecto o cuyo valor por defecto se ha tocado; las genéricas de Laravel
(`DB_*`, `SESSION_*`, `REDIS_*`, `MEMCACHED_HOST`, `MAIL_MAILER/HOST/PORT/USERNAME/PASSWORD`)
no se repiten aquí, se explican solas.

⚠️ Al final hay una sección con variables que **están en `.env.example` pero no se
usan en ningún sitio del código** — para no perder tiempo buscando qué hacen.

## Aplicación

| Variable | Uso |
|---|---|
| `APP_NAME` | Nombre de la aplicación (título, correos, etc.). |
| `APP_ENV` | `local` / `production` /... |
| `APP_KEY` | Clave de cifrado de Laravel. La regeneran `condominios:install` y `doslago:db-reset`. |
| `APP_DEBUG` | Además de mostrar errores detallados, controla qué comandos peligrosos aparecen en `php artisan list` (ver `docs/comandos-artisan.md`: `fakeseed`, `db-reset`). |
| `APP_URL` | URL base de la aplicación. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` / `APP_FAKER_LOCALE` | Idioma de la app / idioma de respaldo / locale de Faker (datos de prueba). |
| `APP_TIMEZONE` | Zona horaria. |

## Base de datos

`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`:
estándar de Laravel. `doslago:db-reset` las lee como valores por defecto y las
sobrescribe al terminar.

## Logs

| Variable | Uso |
|---|---|
| `LOG_CHANNEL` | Canal de log de Laravel (`daily`). |
| `LOG_LEVEL` | Nivel de log de Laravel. |
| `APP_LOG_LEVEL` | Nivel de log propio de la app (`config('settings.log_level')`), independiente del `LOG_LEVEL` de Laravel. |

## Almacenamiento de ficheros (discos)

| Variable | Disco | Uso |
|---|---|---|
| `DOCUMENTOS_ROOT` | `documentos` | Carpeta (relativa a `storage/`) donde se guardan los documentos/facturas adjuntos a proveedores. En L9 era `app/documentos`. |
| `COMS_ROOT` | `coms` | Carpeta donde `condominios:comunidad-exportar` deja los `.zip` de exportación de comunidad. |
| `BACKUPS_ROOT` *(no está en `.env.example`, pero existe en `config/filesystems.php`)* | `backups` | Copias de seguridad completas (spatie/laravel-backup). |
| `BOLETINES_ROOT` *(ídem)* | `boletines` | Legado de L9, sin relación con Comunidades. |

## Facturas de proveedores

| Variable | Uso |
|---|---|
| `FACTURAS_LECTOR_PDF` | Motor para convertir el PDF a texto antes de analizarlo: `pdftotext` (poppler-utils, por defecto), `pdfparser` (librería PHP pura) o `pdfplumber` (Python, vía `storage/app/pyenv`). |
| `ANTHROPIC_API_KEY` | Clave de **console.anthropic.com** (créditos de la organización, no los de uso de claude.ai) para el botón "Generar plantilla con IA" al dar de alta un proveedor. Sin ella, ese botón concreto falla pero el resto de la app funciona igual. |
| `FACTURAS_IA_MODELO` | Modelo usado por ese botón (por defecto `claude-haiku-4-5-20251001`: solo necesita localizar texto literal en un documento corto, no hace falta un modelo caro). |

## Comportamiento de la interfaz

| Variable | Uso |
|---|---|
| `LIST_NOMBRECOMPLETO` | Orden para mostrar nombres completos: `1` = "Nombre Apellidos", `2` = "Apellidos, Nombre". Afecta a `Persona` y `PersonaComunidad`. |
| `RLAU_TAB_STYLE` | `false` (por defecto) = Enter activa el botón por defecto del formulario; `true` = Enter y las flechas izquierda/derecha saltan de campo en campo, como Tab. |
| `TRACK_LOGIN` | Si se registra cada inicio de sesión. |
| `TRACK_NEW_USER_REGISTRATION` | Si se registra cada alta de usuario nuevo. |
| `AUTH_REMEMBER_LIFETIME` | Minutos que dura la cookie de "recuérdame" (por defecto 43200 = 30 días). |

## Otros

| Variable | Uso |
|---|---|
| `SERVER_XESTION` | URL del sistema de gestión (L9) enlazada desde `config('settings.servers.xestion')`. |
| `DEFAULT_MUNICIPIO` | Municipio por defecto en formularios con selector de municipio (`config('settings.municipioDefault')`, por defecto 5327 = Redondela). |
| `TRANSLATIONS_PROVIDER` | Proveedor usado por el paquete de traducciones (`dummy` por defecto). |
| `SUPERADMIN_EMAIL` / `SUPERADMIN_LOGIN` | Datos del usuario superadmin que crean `condominios:install`, `doslago:db-reset` y el seeder `CreateSuperUserSeeder`. |
| `BACKUP_MAIL_TO_ADDRESS` | Dirección a la que `spatie/laravel-backup` avisa si un backup falla. |
| `BACKUP_ARCHIVE_PASSWORD` | Contraseña con la que se cifra el `.zip` del backup. |

## ⚠️ Variables presentes en `.env.example` que no se usan en el código

Comprobado por grep en todo el proyecto (`app/`, `config/`, `routes/`, `database/`):
ninguna de estas se lee en ningún sitio. Son restos del proyecto hermano del que se
partió (uno de gestión escolar/musical — de ahí "boletines", "TICKETPRINTER",
Telegram de alertas...). Se pueden dejar en blanco sin que afecte a nada; si algún
día se necesitan de verdad, habrá que además cablearlas en el código.

- `SERVER_CONTABILIDAD`
- `SERVER_TEST_COLOR`
- `ARCANEDEV_LOGVIEWER_MIDDLEWARE`
- `LOGO_TEXT`, `LOGO_IMG`, `LOGO_ALT`, `MENU_HEADER`
- `MAIL_ENCRYPTION` (Laravel 12 ya no usa esta clave para SMTP)
- `EMAIL_FIRMA`, `EMAIL_FACTURA_FIRMA`
- `EMAIL_SANDBOX`, `EMAIL_SANDBOX_TO`
- `TICKETPRINTER_IP`, `TICKETPRINTER_PORT`, `TICKETPRINTER_IMPRIMIR_AL_GRABAR`
- `ENVIAR_EMAIL_AL_CREAR_FACTURA`, `ENVIAR_EMAIL_AL_CREAR_BOLETIN`
- `TELEGRAM_BOT_USERNAME`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_CHATID`
- `BACKUP_MAIL_FROM_NAME`, `BACKUP_MAIL_FROM_ADDRESS`, `BACKUP_HOUR_ONE`, `BACKUP_HOUR_TWO`, `BACKUP_HOUR_CLEAN`
- `SAVE_USER_LAST_SEEN`
- `BROADCAST_DRIVER` (no hay `config/broadcasting.php` publicado)
- `CACHE_DRIVER` (Laravel 12 lee `CACHE_STORE`, no `CACHE_DRIVER`; el valor real por defecto es `database`, no `file`)

### Un caso aparte: `DEFAULT_PROVINCIA`

Está en `.env.example` pero **no funciona**: `config/settings.php` tiene una errata
(`env('DEFAULT.PROVINCIA', 37)`, con un punto en vez de un guion bajo), así que
nunca lee `DEFAULT_PROVINCIA` y siempre usa el valor por defecto (37 = Pontevedra).
No lo he corregido porque no formaba parte de lo pedido; que quede aquí anotado
para quien decida si merece la pena arreglarlo.
