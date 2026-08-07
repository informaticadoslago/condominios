# Pendientes

Lo que está decidido pero todavía no construido. No es una lista de ideas: lo que entra aquí
es trabajo acordado, con lo que hace falta saber para retomarlo sin releer la conversación en
la que salió.

Cuando algo se termina se borra de aquí; lo que quede escrito en el código o en los otros
documentos de `docs/` ya no hace falta repetirlo.

---

## 1. Datos imprescindibles al enlazar una comunidad

Acordado el 2026-08-07 y sin empezar: al enlazar la comunidad con la contabilidad debería
salir un modal que obligue a rellenar lo que falte, y solo entonces enlazar.

- **Bloquea:** el nombre contable de cada cuenta bancaria de la comunidad, que es lo único
  imprescindible para el primer asiento.
- **No bloquea:** la caja y la cuenta de comisiones. Esa comunidad puede no cobrar nunca en
  efectivo ni tener una devolución; se piden cuando aparezca el primer caso.
- **Avisa, sin bloquear:** que la empresa contable tenga un ejercicio abierto que cubra las
  fechas. Es el fallo que más desconcierta después, porque todo parece bien hasta que el
  asiento se rechaza.

Sitio: `Comunidades\Lista::enlazarContabilidad()`.

## 2. Leer y modificar terceros por la API

Del alta solo está hecho el alta. Falta el resto de lo hablado:

- `GET /api/contabilidad/terceros/{cuenta}` — leer usando la cuenta (`43000001`) como id.
- `PUT /api/contabilidad/terceros/{cuenta}` — modificar; los datos fiscales los manda quien
  llama, la contabilidad no se los pide a nadie.
- En los dos, la misma comprobación que ya lleva el alta: que el token tenga acceso a esa
  empresa contable. Cambiar la cuenta en la URL no puede servir para leer la de otro.

Punto de partida: `App\Http\Controllers\Api\Contabilidad\TerceroContableController` y
`App\Http\Requests\Contabilidad\AltaTerceroRequest`.

## 3. Quién puede crear empresas contables por la API

`asientos`, `ejercicios`, `terceros` y `cuentas-ingreso` ya comprueban rol + habilidad del
token (`User::puedeOperarEnEmpresaContable()`). El que queda fuera es
`POST /api/contabilidad/empresas`: crea la empresa a partir del CIF, así que **todavía no
existe** la empresa cuya habilidad exigir, y `ResolverEmpresaContableRequest::authorize()`
sigue devolviendo `true` —cualquier token válido puede crear empresas contables.

Sin decidir: si el alta la reserva el rol `global`, si hace falta una habilidad aparte del
tipo `empresas:crear` que se conceda al crear el token, o si se deja abierta.

## 4. Enlazar los recibos al aprobar el presupuesto

Ahora los recibos se enlazan **solo a mano**, con la opción del menú de tres puntos de la
lista de recibos (`EnlazarRecibosContabilidad`, que de paso enlaza también los cobros). Esa
opción tiene que seguir existiendo —una comunidad puede enlazarse con la contabilidad cuando
ya tenía recibos emitidos y cobrados—, pero lo normal sería que al aprobar el presupuesto
salieran ya enlazados.

Sitio: `Presupuesto::booted()`, donde ya se pide la cuenta de ingresos y se vuelcan los
recibos, todo en la misma transacción.

## 5. Traducir `menu.Recibos` y `menu.Remesas`

Las dos entradas de menú usan `trans_key('menu.Recibos')` y `trans_key('menu.Remesas')`, y
ninguna de las dos claves está traducida todavía.

## 6. Rehacer la plantilla PDF del mandato SEPA

El contenido y los campos son los correctos; lo que no sirve es cómo queda el PDF que sale de
dompdf. Al retomarlo, partir del `Formulario3.pdf` de referencia y cuidar la maquetación.

---

## Fuera de esto

Los 18 tests de Jetstream de serie (`AuthenticationTest`, `PasswordResetTest`…) fallan desde
antes de todo esto: su `UserFactory` inserta una columna `name` que esta tabla `users` no
tiene. No es un pendiente de contabilidad, pero conviene no confundirlo con uno cuando se
lanza la batería entera.
