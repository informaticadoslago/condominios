# Arquitectura: core + 3 plugins

Separación de la aplicación en un **core** y 3 plugins: **Comunidades**, **EmpresasContables** y **Sociedades**.

Criterio de reparto: lo que aparece en el menú lateral propio de cada plugin (`config/menu_comunidad.php`, `config/menu_contable.php`, `config/menu_sociedad.php`) pertenece a ese plugin. Todo lo demás es core.

## Plugin Comunidades

Módulos: Facturas, Inmuebles, Presupuestos, Recibos, Remesas, ComisionesBancarias, MovimientosBancarios, GruposDeReparto, Propietarios, Proveedores.

## Plugin EmpresasContables

Módulos: AsientosContables, MayorContable, SumasYSaldos, MovimientosContables, PlanDeCuentas, EjerciciosContables.

Incluye también **CuentasContables** (plan de cuentas maestro global, `empresa_contable_id` nulo): aunque su entrada vive en el sidebar global fuera del contexto de una empresa activa, pertenece conceptualmente a este plugin.

## Plugin Sociedades

Contenido aún mínimo (solo su propio dashboard). Pendiente de definir a medida que se desarrolle.

Punto abierto: las **facturas de sociedad** van a generar conflicto de reparto con Comunidades (Facturas ya pertenece a ese plugin) — queda pendiente de decidir cuando se aborde.

## Core

Todo lo que queda en `sidebar.php` (menú global) y no entra en ninguno de los menús anteriores:

- AdministracionSistema, Catalogos, Dashboard, Maestros, Profile, TokensApi.
- El índice/alta de cada plugin (listas de Comunidades, Sociedades, EmpresasContables) y sus `*ContextoController` (entrar/salir de contexto: `ComunidadContextoController`, `SociedadContextoController`, `EmpresaContableContextoController`).
