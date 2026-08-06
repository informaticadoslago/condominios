# Condominios dosLago

Aplicación web de administración de comunidades de propietarios: gestión de comunidades,
inmuebles, propietarios, presupuestos, recibos y facturas, con un módulo de contabilidad
propio detrás.

## Qué hace

- **Comunidades**: comunidades, inmuebles, propietarios, grupos de reparto y cuentas
  bancarias, con mandatos SEPA por inmueble.
- **Presupuestos y recibos**: presupuesto por ejercicio y coeficientes de reparto; al
  aprobarlo se generan los recibos de cada inmueble, que luego se cobran o se remesan.
- **Facturas de proveedor**: se sube el PDF y se extraen los datos por plantilla, con ayuda
  de IA para generar la plantilla de cada proveedor.
- **Contabilidad**: plan de cuentas, ejercicios, asientos y terceros. Es un módulo
  independiente: no conoce la gestión y se le habla siempre por su API.
- **Administración de sistema**: personas, usuarios, roles, permisos, catálogos maestros,
  informes y copias de seguridad.

## Stack técnico

- PHP 8.4 · Laravel 13
- Livewire 4 + Flux (TallStackUI)
- Jetstream, Sanctum, Spatie Permission / Activitylog / Backup
- MySQL o MariaDB

## Requisitos del sistema

Además de PHP, Composer, Node y la base de datos:

| Binario | Para qué | ¿Obligatorio? |
|---|---|---|
| `pdftotext` (**poppler-utils**) | Motor por defecto de lectura de facturas en PDF | Sí, salvo que cambies `FACTURAS_LECTOR_PDF` |
| `pdfimages` (**poppler-utils**) | Extraer el QR Verifactu de las facturas | Sí, para leer el QR |
| `python3` + `pdfplumber` | Motor alternativo de lectura, en el venv de `storage/app/pyenv` | Solo si usas `FACTURAS_LECTOR_PDF=pdfplumber` |

En Debian/Ubuntu:

```bash
sudo apt install poppler-utils
```

El motor de lectura de PDF se elige en [config/facturas.php](config/facturas.php) con la
variable `FACTURAS_LECTOR_PDF`: `pdftotext` (por defecto), `pdfparser` (PHP puro, sin
binarios) o `pdfplumber`.

## Puesta en marcha

Instalación completa y guiada, que si hace falta crea la base de datos y su usuario:

```bash
php artisan condominios:install
```

⚠️ **Vacía la base de datos actual.** Para un arranque manual sin tocar datos existentes está
`composer setup`, que instala dependencias, genera la `APP_KEY`, migra y compila los assets.

Entorno de desarrollo (servidor, cola, logs y Vite a la vez):

```bash
composer dev
```

Tests:

```bash
composer test
```

## Carga inicial

`php artisan db:seed` deja la base con lo mínimo para funcionar
([DatabaseSeeder](database/seeders/DatabaseSeeder.php)):

| Seeder | Qué carga |
|---|---|
| `PlanCuentasComunidadesSeeder` | Plantilla de plan de cuentas de una comunidad |
| `EntidadesBancariasSeeder` | Entidades bancarias |
| `FormasDePagoSeeder` | Formas de pago |
| `GeneroSeeder` | Géneros |
| `TipoDocumentoIdentificativoSeeder` | Tipos de documento identificativo |
| `PermisosYRolesInicialSeeder` | Roles y permisos de partida |

Las cuentas del plan se cargan como **cuentas maestras** (sin empresa contable asignada): son
la plantilla con la que arranca el plan de una empresa nueva, no las cuentas de nadie en
concreto. Los códigos y el porqué de cada uno están en
[docs/plan-de-cuentas.md](docs/plan-de-cuentas.md).

Los seeders `Demo*` y el comando `condominios:fakeseed` generan datos ficticios y solo están
disponibles en modo debug.

## API de contabilidad

La contabilidad es un módulo independiente con su propia puerta de entrada, bajo
`/api/contabilidad` y autenticada con Sanctum:

| Método | Ruta | Qué hace |
|---|---|---|
| `POST` | `/api/contabilidad/empresas` | Alta o recuperación de la empresa contable por CIF |
| `POST` | `/api/contabilidad/ejercicios` | Apertura de un ejercicio |
| `POST` | `/api/contabilidad/asientos` | Registro de un asiento |

Desde dentro de la aplicación no se pasa por HTTP: se llama directamente a
`RegistrarAsientoService`, que permite compartir transacción con la operación que ha
originado el asiento. El contrato completo, con ejemplos, idempotencia y errores, está en
[docs/api-contabilidad.md](docs/api-contabilidad.md).

## Documentación

| Documento | Contenido |
|---|---|
| [docs/api-contabilidad.md](docs/api-contabilidad.md) | Contrato de la API de contabilidad: asientos, terceros, idempotencia y errores |
| [docs/plan-de-cuentas.md](docs/plan-de-cuentas.md) | Cómo se eligen los códigos de cuenta, qué dice el PGC y la plantilla de comunidades |
| [docs/comandos-artisan.md](docs/comandos-artisan.md) | Todos los comandos de consola propios, con su nivel de riesgo |
| [docs/variables-entorno.md](docs/variables-entorno.md) | Las variables de `.env` propias del proyecto |

## Licencia

Este proyecto está licenciado bajo la licencia MIT. Consulta el fichero [LICENSE](LICENSE)
para más detalles.
