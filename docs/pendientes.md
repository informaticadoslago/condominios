# Pendientes

Lo que está decidido pero todavía no construido. No es una lista de ideas: lo que entra aquí
es trabajo acordado, con lo que hace falta saber para retomarlo sin releer la conversación en
la que salió.

Cuando algo se termina se borra de aquí; lo que quede escrito en el código o en los otros
documentos de `docs/` ya no hace falta repetirlo.

---

## 1. El asiento del cobro

Hoy se le manda a la contabilidad la **emisión** del recibo (el propietario al debe, la cuenta
de ingresos del presupuesto al haber), pero **cobrarlo no genera ningún asiento**: falta el
banco o la caja contra el propietario.

- Lo que ya existe: `App\Services\Recibos\RegistrarCobro` crea la fila en `cobros` y suma al
  `importe_pagado` del recibo. La tabla `cobros` está pensada justo para esto — cada fila es
  el hecho fechado que la contabilidad referencia, y por eso una devolución es otra fila con
  importe negativo en vez de una corrección.
- Lo que falta: un asiento por cobro (o por tanda de cobros), con referencia `cobro:N` para
  que reenviarlo no lo duplique, y la cuenta de tesorería que corresponda según la forma de
  pago (banco para transferencia y remesa, caja para efectivo).
- A decidir: si la cuenta de tesorería sale de un catálogo, de la cuenta bancaria de la
  comunidad, o se pregunta al cobrar.

La **devolución** entra por el mismo sitio y con la misma forma: es otra fila de `cobros`, con
importe negativo, y su asiento es el del cobro al revés (el propietario vuelve al debe). Ojo
con un detalle acordado: en gestión el importe va negativo, pero **a la contabilidad se pasa
en positivo**, cambiando de columna — `RegistrarAsientoService` rechaza los negativos.
Depende del punto 6.

## 2. Leer y modificar terceros por la API

Del alta solo está hecho el alta. Falta el resto de lo hablado:

- `GET /api/contabilidad/terceros/{cuenta}` — leer usando la cuenta (`43000001`) como id.
- `PUT /api/contabilidad/terceros/{cuenta}` — modificar; los datos fiscales los manda quien
  llama, la contabilidad no se los pide a nadie.
- En los dos, la misma comprobación que ya lleva el alta: que el token tenga acceso a esa
  empresa contable. Cambiar la cuenta en la URL no puede servir para leer la de otro.

Punto de partida: `App\Http\Controllers\Api\Contabilidad\TerceroContableController` y
`App\Http\Requests\Contabilidad\AltaTerceroRequest`.

## 3. `POST /api/contabilidad/asientos` no comprueba el acceso

`RegistrarAsientoRequest::authorize()` devuelve `true`: cualquier token válido puede escribir
en la contabilidad de cualquier empresa, porque `empresa_contable_id` viaja en el cuerpo.

Los endpoints nuevos (`terceros`, `cuentas-ingreso`) ya lo comprueban con
`$this->user()->empresasContablesAccesibles()`. Hay que igualar los tres, y de paso mirar
`empresas` y `ejercicios`.

## 4. Enlazar los recibos al aprobar el presupuesto

Ahora los recibos se enlazan **solo a mano**, con la opción del menú de tres puntos de la
lista de recibos (`EnlazarRecibosContabilidad`). Esa opción tiene que seguir existiendo —una
comunidad puede enlazarse con la contabilidad cuando ya tenía recibos emitidos—, pero lo
normal sería que al aprobar el presupuesto salieran ya enlazados.

Sitio: `Presupuesto::booted()`, donde ya se pide la cuenta de ingresos y se vuelcan los
recibos, todo en la misma transacción.

## 5. Traducir `menu.Recibos` y `menu.Remesas`

Las dos entradas de menú usan `trans_key('menu.Recibos')` y `trans_key('menu.Remesas')`, y
ninguna de las dos claves está traducida todavía.

## 6. Registrar las devoluciones

De la remesa está hecho todo el camino de ida —generarla, el pain.008, los recibos a
Enviado—, pero **no hay forma de registrar lo que vuelve**. Es lo que bloquea al punto 1: sin
la devolución no hay hecho al que colgarle el asiento, y el recibo no vuelve a quedar
disponible para la siguiente remesa.

- Lo que ya existe: `lineas_remesas` tiene `fecha_devolucion` y `motivo_devolucion`, la lista
  de remesas ya cuenta cuántas volvieron, y `GeneradorRemesa` **ya cuenta con esto**: un
  recibo devuelto vuelve a entrar en la siguiente remesa con una línea nueva (filtra por
  `lineasRemesas` sin `fecha_devolucion`, o sea, las que siguen en vuelo).
- Lo que falta: el servicio que marca la línea como devuelta —fecha, motivo, y la fila
  negativa en `cobros` que devuelve el saldo al recibo y lo saca de Cobrado—, y por dónde se
  hace: a mano una a una, o leyendo el fichero de devoluciones que manda el banco (pain.002 /
  el cuaderno que use la entidad).
- A decidir: eso último, si se teclea o se importa.

## 7. Los dos correos de los recibos

Acordado desde el principio y sin empezar: al emitir los recibos se avisa por correo, con un
texto distinto según cómo pague cada uno —a los de **transferencia** hay que pedirles el
dinero, a los de **remesa** solo avisarles de que se les va a cargar y cuándo.

- Lo que ya existe: el layout `resources/views/components/emails/layout.blade.php`, que
  incrusta el `.css` hermano de cada plantilla, y `VerificacionCorreoPropietario` como
  ejemplo del patrón (Mailable encolado en `EnviarCorreo`, idioma por `locale()`).
- A decidir: si el correo lleva el recibo en PDF adjunto o basta el cuerpo, y desde dónde se
  dispara — al generar la remesa, desde la lista de recibos, o las dos.
- Trampa ya pagada: en un Mailable, una propiedad pública **no puede llamarse igual** que una
  clave de los datos de la vista; Laravel escribe las propiedades encima y la plantilla acaba
  imprimiendo el modelo entero.

## 8. Rehacer la plantilla PDF del mandato SEPA

El contenido y los campos son los correctos; lo que no sirve es cómo queda el PDF que sale de
dompdf. Al retomarlo, partir del `Formulario3.pdf` de referencia y cuidar la maquetación.

---

## Fuera de esto

Los 18 tests de Jetstream de serie (`AuthenticationTest`, `PasswordResetTest`…) fallan desde
antes de todo esto: su `UserFactory` inserta una columna `name` que esta tabla `users` no
tiene. No es un pendiente de contabilidad, pero conviene no confundirlo con uno cuando se
lanza la batería entera.
