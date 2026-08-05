# API de contabilidad

Manual de entrada de asientos al módulo contable.

La contabilidad es independiente de quien le mete asientos: no conoce comunidades, ni
inmuebles, ni recibos, ni remesas. Solo sabe de empresas contables, ejercicios, cuentas,
terceros, asientos y apuntes. Cualquier sistema —la gestión de comunidades de esta misma
aplicación, un importador, o un programa ajeno— entra por el mismo sitio y se encuentra
las mismas reglas.

## Índice

| Apartado | Contenido |
|---|---|
| [Las dos puertas](#las-dos-puertas) | Cuándo usar HTTP y cuándo el servicio interno |
| [Autenticación](#autenticación) | Token Sanctum |
| [Registrar un asiento](#registrar-un-asiento) | El endpoint, campos y respuestas |
| [Las líneas](#las-líneas) | Cuenta directa o tercero |
| [Importes en céntimos](#importes-en-céntimos) | Nunca euros, nunca decimales |
| [Idempotencia](#idempotencia) | Reenviar el mismo evento no duplica |
| [Terceros y subcuentas](#terceros-y-subcuentas) | Alta, clases y numeración |
| [Errores](#errores) | Códigos de estado y qué los provoca |
| [Uso desde dentro](#uso-desde-dentro-de-la-aplicación) | El servicio, con transacción compartida |
| [Carga inicial](#carga-inicial) | Dar de alta terceros en bloque |

---

## Las dos puertas

Hay **una sola lógica** —`RegistrarAsientoService`— con dos formas de llegar a ella:

| Puerta | Quién la usa | Qué gana | Qué pierde |
|---|---|---|---|
| `POST /api/contabilidad/asientos` | Sistemas externos, importadores, otros lenguajes | Funciona desde cualquier sitio | No comparte transacción |
| `RegistrarAsientoService::ejecutar()` | Código de esta misma aplicación | Transacción compartida: o entra todo o no entra nada | Solo desde dentro |

Las reglas contables están en el servicio, no en el controlador ni en el `FormRequest`.
Quien entra por HTTP y quien llama al servicio pasan exactamente por las mismas
comprobaciones.

La independencia no la da el HTTP, la da el contrato de datos: el servicio recibe un DTO
neutro (`DatosAsiento`), nunca un modelo de la gestión.

---

## Autenticación

El endpoint va bajo `auth:sanctum`. Se manda el token en la cabecera:

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Sin token válido, `401`.

---

## Registrar un asiento

```
POST /api/contabilidad/asientos
```

```json
{
  "empresa_contable_id": 3,
  "ejercicio": "2026",
  "fecha": "2026-01-31",
  "diario": "REC",
  "concepto": "Recibo enero 2026 - Piso 1A",
  "referencia": { "tipo": "recibo", "id": "1234", "evento": "emision" },
  "lineas": [
    { "tercero": { "tipo": "propietario", "id": "17" }, "debe": 992 },
    { "cuenta": "70500001", "haber": 992 }
  ]
}
```

### Campos de la cabecera

| Campo | Obligatorio | Tipo | Notas |
|---|---|---|---|
| `empresa_contable_id` | sí | entero | Debe existir en `empresas_contables` |
| `ejercicio` | sí | texto | El `nombre` del ejercicio, no un id. Ej. `"2026"` |
| `fecha` | sí | `AAAA-MM-DD` | Tiene que caer dentro del ejercicio |
| `concepto` | sí | texto (255) | |
| `diario` | no | texto (10) | Etiqueta libre: `REC`, `COM`, `BAN`… |
| `referencia` | no | objeto | Clave de idempotencia, ver más abajo |
| `crear_terceros_desconocidos` | no | booleano | Por defecto `false` |
| `lineas` | sí | array | Mínimo 2 |

### Respuesta correcta

`201 Created` si el asiento se ha creado ahora, `200 OK` si esa referencia ya estaba
registrada y se devuelve el asiento existente.

```json
{
  "id": 45,
  "numero": 12,
  "ejercicio": "2026",
  "fecha": "2026-01-31",
  "diario": "REC",
  "concepto": "Recibo enero 2026 - Piso 1A",
  "referencia": { "tipo": "recibo", "id": "1234", "evento": "emision" },
  "lineas": [
    { "cuenta": "43000042", "debe": 992, "haber": 0, "concepto": null },
    { "cuenta": "70500001", "debe": 0, "haber": 992, "concepto": null }
  ]
}
```

El `numero` es correlativo por ejercicio y lo asigna la contabilidad, no quien llama.

---

## Las líneas

Cada línea indica su cuenta **de una de dos maneras, nunca las dos a la vez**:

**Por código de cuenta**, cuando ya se sabe cuál es:

```json
{ "cuenta": "70500001", "haber": 992, "concepto": "Cuota ordinaria enero" }
```

**Por tercero**, cuando la cuenta es la subcuenta de alguien y no se quiere conocer su
código:

```json
{ "tercero": { "tipo": "propietario", "id": "17" }, "debe": 992 }
```

### Reglas de las líneas

- Mínimo dos líneas por asiento.
- Cada línea lleva `cuenta` **o** `tercero`, nunca ambos ni ninguno.
- Cada línea lleva importe en `debe` **o** en `haber`, nunca en los dos, nunca cero.
- Ningún importe negativo: en partida doble se cambia de columna, no se pone signo.
- `suma(debe) == suma(haber)`, exacto.

Si algo de esto falla, `422` y no se graba nada.

---

## Importes en céntimos

**Todos los importes son enteros de céntimos.** 9,92 € se manda como `992`.

Nunca decimales, nunca cadenas con coma, nunca euros. La aritmética del cuadre es entera
y la igualdad es exacta, sin tolerancias ni redondeos. La respuesta también devuelve
céntimos.

---

## Idempotencia

El bloque `referencia` identifica el hecho externo que originó el asiento:

```json
"referencia": { "tipo": "recibo", "id": "1234", "evento": "emision" }
```

La terna es única dentro de una empresa contable. Reenviar exactamente el mismo
`(tipo, id, evento)` **no crea un segundo asiento**: devuelve `200` con el que ya existía.
Eso hace que reintentar tras un corte de red sea seguro.

Los tres valores son texto libre. La contabilidad no los interpreta ni mantiene una lista
de eventos conocidos, solo los compara.

> **Cuidado con qué se usa de referencia.** Para hechos que pueden repetirse sobre el
> mismo objeto —un recibo que se devuelve, se vuelve a remesar y se devuelve otra vez— no
> sirve el id del recibo. Hay que usar el identificador del movimiento concreto, por
> ejemplo el *end-to-end id* de la línea del fichero de retorno del banco.

El `referencia` es opcional: un asiento manual no lleva ninguna, y puede haber tantos
asientos sin referencia como haga falta.

---

## Terceros y subcuentas

Un tercero es una ficha dentro de la contabilidad con su NIF, su razón social y su
subcuenta. La contabilidad los necesita para sus propios libros e informes, y por eso
**los datos fiscales los manda quien da el alta**: la contabilidad no se los pide a nadie.

```json
{
  "tercero": {
    "tipo": "propietario",
    "id": "17",
    "clase": "cliente",
    "nif": "12345678Z",
    "razon_social": "García Pérez, Antonio"
  },
  "debe": 992
}
```

| Campo | Para qué sirve |
|---|---|
| `tipo` + `id` | La etiqueta opaca con la que **tu** sistema reconoce al tercero. La contabilidad la guarda y la compara, pero no la interpreta |
| `clase` | Vocabulario contable: decide de qué grupo cuelga la subcuenta. Solo hace falta al crearlo |
| `nif`, `razon_social` | Datos fiscales para libros registro y modelo 347. Solo al crearlo |

### Clases disponibles

| `clase` | Grupo | Denominación |
|---|---|---|
| `proveedor` | `4000` | Proveedores |
| `acreedor` | `4100` | Acreedores por prestaciones de servicios |
| `cliente` | `4300` | Clientes |
| `deudor` | `4400` | Deudores varios |

### Alta de la subcuenta

Si el tercero ya tiene subcuenta, se usa. Si no la tiene:

- Sin `crear_terceros_desconocidos`, la petición falla con `422`. Es lo correcto en el día
  a día: un tercero desconocido casi siempre es un error de quien llama, y es preferible
  el error a ensuciar el plan de cuentas con subcuentas fantasma.
- Con `"crear_terceros_desconocidos": true`, se crea el tercero y su subcuenta en la misma
  transacción. Para eso hace falta mandar al menos la `clase`.

El código de subcuenta es de 8 dígitos: 4 de grupo más 4 de correlativo. El primer cliente
es el `43000001`, el segundo el `43000002`. **Ese correlativo no tiene ninguna relación con
el `id` del tercero**, y no hay que suponerla. Al agotar las 9.999 subcuentas de un grupo
la petición falla con `409`; no se salta al grupo siguiente, para que un grupo siga siendo
un prefijo único en informes y exportaciones.

---

## Errores

| Estado | Cuándo |
|---|---|
| `401` | Falta el token o no es válido |
| `409` | El ejercicio está cerrado, o se han agotado las 9.999 subcuentas del grupo |
| `422` | El asiento no cuadra, una línea está mal formada, la fecha cae fuera del ejercicio, la cuenta no existe, el ejercicio no existe, o el tercero no existe y no se autorizó crearlo |

Los `422` y `409` del servicio devuelven el motivo en texto:

```json
{ "message": "El asiento no cuadra: 9,92 de Debe frente a 10,00 de Haber." }
```

Los errores de forma del JSON (falta un campo, un tipo no cuadra) devuelven el `422`
estándar de Laravel con el detalle por campo en `errors`.

---

## Uso desde dentro de la aplicación

Desde código de esta misma aplicación conviene llamar al servicio directamente, porque así
el asiento y lo que lo originó comparten transacción:

```php
use App\Services\Contabilidad\DatosAsiento;
use App\Services\Contabilidad\RegistrarAsientoService;
use Illuminate\Support\Facades\DB;

public function emitir(Recibo $recibo, RegistrarAsientoService $registrar): void
{
    DB::transaction(function () use ($recibo, $registrar) {
        $recibo->marcarEmitido();

        $registrar->ejecutar(DatosAsiento::desdeArray([
            'empresa_contable_id' => $recibo->empresa_contable_id,
            'ejercicio'           => $recibo->ejercicio,
            'fecha'               => $recibo->fecha_emision->toDateString(),
            'diario'              => 'REC',
            'concepto'            => "Recibo {$recibo->periodo}",
            'referencia'          => ['tipo' => 'recibo', 'id' => (string) $recibo->id, 'evento' => 'emision'],
            'lineas' => [
                ['tercero' => ['tipo' => 'propietario', 'id' => (string) $recibo->propietario_id], 'debe' => $recibo->importe],
                ['cuenta' => '70500001', 'haber' => $recibo->importe],
            ],
        ]));
    });
}
```

Si el asiento falla, el recibo se deshace con él. Por HTTP eso no es posible: son dos
transacciones separadas, y de ahí que la idempotencia sea imprescindible.

Las excepciones que puede lanzar el servicio, todas en `App\Exceptions`:

| Excepción | Motivo |
|---|---|
| `AsientoInvalidoException` | Descuadre, línea mal formada, fecha fuera del ejercicio |
| `EjercicioCerradoException` | El ejercicio está cerrado |
| `EjercicioContableDesconocidoException` | No existe ese ejercicio en esa empresa |
| `CuentaContableDesconocidaException` | Un código de cuenta no existe en esa empresa |
| `TerceroContableDesconocidoException` | Tercero sin subcuenta y sin autorización para crearla |
| `SubcuentasAgotadasException` | Las 9.999 del grupo están usadas |

---

## Carga inicial

Cuando un sistema ya tiene terceros de antes, hay que darles subcuenta una primera vez.
Ese bucle **vive en el lado de quien llama**, no en la contabilidad: la contabilidad no
puede recorrer una tabla que no conoce.

El procedimiento es mandar los asientos de arranque —o simplemente los primeros asientos
reales— con `crear_terceros_desconocidos` activado y los datos fiscales completos en cada
línea de tercero. A partir de ahí, cada alta nueva crea su subcuenta en el momento y la
bandera vuelve a sobrar.
