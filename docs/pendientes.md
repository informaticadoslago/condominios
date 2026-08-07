# Pendientes

Lo que está decidido pero todavía no construido. No es una lista de ideas: lo que entra aquí
es trabajo acordado, con lo que hace falta saber para retomarlo sin releer la conversación en
la que salió.

Cuando algo se termina se borra de aquí; lo que quede escrito en el código o en los otros
documentos de `docs/` ya no hace falta repetirlo.

---

## 1. Leer y modificar terceros por la API

Del alta solo está hecho el alta. Falta el resto de lo hablado:

- `GET /api/contabilidad/terceros/{cuenta}` — leer usando la cuenta (`43000001`) como id.
- `PUT /api/contabilidad/terceros/{cuenta}` — modificar; los datos fiscales los manda quien
  llama, la contabilidad no se los pide a nadie.
- En los dos, la misma comprobación que ya lleva el alta: que el token tenga acceso a esa
  empresa contable. Cambiar la cuenta en la URL no puede servir para leer la de otro.

Punto de partida: `App\Http\Controllers\Api\Contabilidad\TerceroContableController` y
`App\Http\Requests\Contabilidad\AltaTerceroRequest`.

## 2. Enlazar los recibos al aprobar el presupuesto

**Pausado el 2026-08-07.** Es un paso importante: significa que todo sale del presupuesto
aprobado en la junta, y eso se decide con calma.

Ahora los recibos se enlazan **solo a mano**, con la opción del menú de tres puntos de la
lista de recibos (`EnlazarRecibosContabilidad`, que de paso enlaza también los cobros). Esa
opción tiene que seguir existiendo —una comunidad puede enlazarse con la contabilidad cuando
ya tenía recibos emitidos y cobrados—, pero lo normal sería que al aprobar el presupuesto
salieran ya enlazados.

Sitio: `Presupuesto::booted()`, donde ya se pide la cuenta de ingresos y se vuelcan los
recibos, todo en la misma transacción.

## 3. Traducir las claves nuevas del menú

`menu.Recibos`, `menu.Remesas` y `menu.Tokens de API` se usan con `trans_key()` en
`config/sidebar.php` y ninguna está traducida todavía. Sin prisa: hay un módulo que
traduce todo lo traducible de una vez, esto se hace cuando le toque.

## 4. Rehacer la plantilla PDF del mandato SEPA

El contenido y los campos son los correctos; lo que no sirve es cómo queda el PDF que sale de
dompdf. Al retomarlo, partir del `Formulario3.pdf` de referencia y cuidar la maquetación.

---

## Fuera de esto

Los 18 tests de Jetstream de serie (`AuthenticationTest`, `PasswordResetTest`…) fallan desde
antes de todo esto: su `UserFactory` inserta una columna `name` que esta tabla `users` no
tiene. No es un pendiente de contabilidad, pero conviene no confundirlo con uno cuando se
lanza la batería entera.
