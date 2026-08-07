# Pendientes

Lo que está decidido pero todavía no construido. No es una lista de ideas: lo que entra aquí
es trabajo acordado, con lo que hace falta saber para retomarlo sin releer la conversación en
la que salió.

Cuando algo se termina se borra de aquí; lo que quede escrito en el código o en los otros
documentos de `docs/` ya no hace falta repetirlo.

---

## 1. Los cobros en efectivo no llegan a la contabilidad

`EnlazarCobrosContabilidad` lleva a la contabilidad los cobros por banco —la remesa entera en
un asiento, cada transferencia suelta en el suyo— y las devoluciones con su comisión. El
**efectivo se queda fuera** y se cuenta como omitido: no hay cuenta de caja donde meterlo.

- Lo que falta: la subcuenta de caja (grupo `5700`) y por dónde se elige. La de bancos sale de
  la cuenta corriente de la comunidad, que tiene su `nombre_contable` en datos financieros;
  para la caja no hay nada equivalente porque no es una cuenta bancaria.
- Sitio: `ResolverCuentaTesoreriaService`, que hoy solo tiene `banco()`, y el filtro
  `esEnlazable()` de `EnlazarCobrosContabilidad`.

## 2. Datos imprescindibles al enlazar una comunidad

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

## 3. Leer y modificar terceros por la API

Del alta solo está hecho el alta. Falta el resto de lo hablado:

- `GET /api/contabilidad/terceros/{cuenta}` — leer usando la cuenta (`43000001`) como id.
- `PUT /api/contabilidad/terceros/{cuenta}` — modificar; los datos fiscales los manda quien
  llama, la contabilidad no se los pide a nadie.
- En los dos, la misma comprobación que ya lleva el alta: que el token tenga acceso a esa
  empresa contable. Cambiar la cuenta en la URL no puede servir para leer la de otro.

Punto de partida: `App\Http\Controllers\Api\Contabilidad\TerceroContableController` y
`App\Http\Requests\Contabilidad\AltaTerceroRequest`.

## 4. `POST /api/contabilidad/asientos` no comprueba el acceso

`RegistrarAsientoRequest::authorize()` devuelve `true`: cualquier token válido puede escribir
en la contabilidad de cualquier empresa, porque `empresa_contable_id` viaja en el cuerpo.

Los endpoints nuevos (`terceros`, `cuentas-ingreso`) ya lo comprueban con
`$this->user()->empresasContablesAccesibles()`. Hay que igualar los tres, y de paso mirar
`empresas` y `ejercicios`.

## 5. Enlazar los recibos al aprobar el presupuesto

Ahora los recibos se enlazan **solo a mano**, con la opción del menú de tres puntos de la
lista de recibos (`EnlazarRecibosContabilidad`, que de paso enlaza también los cobros). Esa
opción tiene que seguir existiendo —una comunidad puede enlazarse con la contabilidad cuando
ya tenía recibos emitidos y cobrados—, pero lo normal sería que al aprobar el presupuesto
salieran ya enlazados.

Sitio: `Presupuesto::booted()`, donde ya se pide la cuenta de ingresos y se vuelcan los
recibos, todo en la misma transacción.

## 6. Traducir `menu.Recibos` y `menu.Remesas`

Las dos entradas de menú usan `trans_key('menu.Recibos')` y `trans_key('menu.Remesas')`, y
ninguna de las dos claves está traducida todavía.

## 7. Rehacer la plantilla PDF del mandato SEPA

El contenido y los campos son los correctos; lo que no sirve es cómo queda el PDF que sale de
dompdf. Al retomarlo, partir del `Formulario3.pdf` de referencia y cuidar la maquetación.

---

## Fuera de esto

Los 18 tests de Jetstream de serie (`AuthenticationTest`, `PasswordResetTest`…) fallan desde
antes de todo esto: su `UserFactory` inserta una columna `name` que esta tabla `users` no
tiene. No es un pendiente de contabilidad, pero conviene no confundirlo con uno cuando se
lanza la batería entera.
