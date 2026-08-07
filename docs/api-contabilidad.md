# API de contabilidad

Manual de entrada al módulo contable: alta de empresas, apertura de ejercicios y registro
de asientos.

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
| [Dar de alta una empresa](#dar-de-alta-una-empresa) | Nombre y CIF, devuelve el id |
| [Abrir un ejercicio](#abrir-un-ejercicio) | Segundo paso, obligatorio antes del primer asiento |
| [Dar de alta un tercero](#dar-de-alta-un-tercero) | NIF y devuelve su subcuenta, sin registrar ningún asiento |
| [La cuenta de un presupuesto o una derrama](#la-cuenta-de-un-presupuesto-o-una-derrama) | Contra qué cuenta de ingresos se cobra |
| [La cuenta de un banco](#la-cuenta-de-un-banco) | Dónde entra el dinero cobrado. Solo por servicio |
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

Cada operación tiene **una sola lógica**, en un servicio, con dos formas de llegar a ella:

| Puerta | Quién la usa | Qué gana | Qué pierde |
|---|---|---|---|
| `POST /api/contabilidad/asientos` | Sistemas externos, importadores, otros lenguajes | Funciona desde cualquier sitio | No comparte transacción |
| `RegistrarAsientoService::ejecutar()` | Código de esta misma aplicación | Transacción compartida: o entra todo o no entra nada | Solo desde dentro |
| `POST /api/contabilidad/empresas` | Igual, para dar de alta la empresa | | |
| `ResolverEmpresaContableService::ejecutar()` | Igual, desde dentro | | |
| `POST /api/contabilidad/ejercicios` | Igual, para abrir un ejercicio | | |
| `AbrirEjercicioContableService::ejecutar()` | Igual, desde dentro | | |
| `POST /api/contabilidad/terceros` | Igual, para dar de alta a quien paga | | |
| `ResolvedorCuentasService::resolver()` | Igual, desde dentro | | |
| `POST /api/contabilidad/cuentas-ingreso` | Igual, para el concepto por el que se cobra | | |
| `ResolverCuentaIngresoService::ejecutar()` | Igual, desde dentro | | |

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

### Un token, una empresa

Cada token vale para **una sola empresa contable**, la que se elige al crearlo. Se crean
desde la aplicación, en el menú de la cuenta → **Tokens de API**, y solo se puede elegir
entre las empresas que abre el rol de quien entra. Un usuario que trabaje con tres
empresas se hace tres tokens.

Un usuario no puede tener dos tokens iguales —misma empresa y mismo alcance—: el segundo
no aportaría nada y sería otra puerta más. Para cambiarlo, se revoca el que hay y se hace
otro. Sí puede tener uno de solo lectura y otro de escritura para la misma empresa.

Al llamar se comprueban dos cosas distintas, y hacen falta las dos:

- el **rol** del usuario dueño del token, que es a qué empresas puede entrar hoy y se le
  puede quitar;
- la **habilidad** del token (`empresa-contable:{id}`), que es la empresa que eligió al
  crearlo.

Solo con el rol, un token filtrado abriría todas sus empresas; solo con la habilidad,
quitarle el acceso a alguien no caducaría sus tokens viejos. Si el
`empresa_contable_id` del cuerpo no es el del token, la respuesta es `403` aunque el
usuario tenga acceso a esa empresa: cambiar ese número en el JSON no sirve para escribir
en la contabilidad de otro.

### Leer y escribir

Todo lo que no es un `GET` exige además la habilidad `contabilidad-escribir`, que se
elige al crear el token (**Puede: leer y escribir** / **solo leer**). Un token de solo
lectura consulta la contabilidad de su empresa, pero no mete un asiento ni da de alta
nada: `403`.

Dentro de la API no se miran los permisos de la aplicación. El token entra como su
dueño, pero lo que puede hacer lo deciden sus habilidades, no sus roles.

El token se enseña **una sola vez**, al crearlo; en la base solo queda su hash. Si se
pierde, se revoca y se hace otro.

**Caducidad.** Los tokens nacen con la fecha de caducidad que fije el administrador en
*Administración del sistema → Tokens de API* (30 días, 1 año… o ninguna). Es la de ese
momento: cambiar el ajuste después no alarga ni acorta los que ya existen. Un token
caducado da `401`, igual que uno inventado. Desde esa misma pantalla se revocan los de
cualquier usuario.

---

## Dar de alta una empresa

Antes de mandar ningún asiento hace falta la empresa contable a la que van. Se pide por
**nombre y CIF**, y la contabilidad devuelve su id.

Es el único endpoint sin la comprobación de empresa de arriba: aquí la empresa todavía no
existe, así que no hay habilidad que exigir. De momento le vale cualquier token válido
(ver `docs/pendientes.md`).

```
POST /api/contabilidad/empresas
```

```json
{
  "cif": "H12345678",
  "razon_social": "Comunidad de Propietarios Los Lagos"
}
```

### Respuesta

`201 Created` si se ha creado ahora, `200 OK` si ese CIF ya tenía empresa.

```json
{
  "id": 3,
  "cif": "H12345678",
  "razon_social": "Comunidad de Propietarios Los Lagos"
}
```

El `id` que devuelve es el que se manda luego como `empresa_contable_id` en cada asiento.

### El CIF es la clave

Repetir la llamada **no crea una segunda empresa**: el CIF identifica a la empresa
contable, y dos peticiones con el mismo CIF devuelven siempre la misma. Se normaliza antes
de comparar —mayúsculas, sin espacios ni guiones—, así que `h-12 345 678` y `H12345678` son
el mismo.

Si la empresa ya existe, la `razon_social` que se manda **se ignora**: quien lleva los
libros decide cómo se llama, no quien pregunta por ella. Para cambiarla hay que editarla
en la contabilidad.

> **Lo que esto NO deja hecho.** La empresa nace con su plan de cuentas copiado del plan
> global, pero **sin ejercicio contable abierto**, y sin ejercicio ningún asiento entra
> (`422`, ejercicio desconocido). El ejercicio se abre en una segunda llamada, la de
> aquí debajo.

---

## Abrir un ejercicio

Segundo paso, y obligatorio antes del primer asiento. Va aparte del alta de la empresa a
propósito: **la contabilidad no inventa las fechas de nadie.** Un año natural, un
ejercicio partido o varios seguidos al migrar datos viejos son decisiones de quien lleva
los libros, no del alta.

```
POST /api/contabilidad/ejercicios
```

```json
{
  "empresa_contable_id": 3,
  "nombre": "2026",
  "fecha_inicio": "2026-01-01",
  "fecha_fin": "2026-12-31"
}
```

| Campo | Obligatorio | Tipo | Notas |
|---|---|---|---|
| `empresa_contable_id` | sí | entero | El que devolvió el alta de la empresa |
| `nombre` | sí | texto (50) | Con esto se pedirán luego los asientos. Ej. `"2026"` |
| `fecha_inicio` | sí | `AAAA-MM-DD` | |
| `fecha_fin` | sí | `AAAA-MM-DD` | No puede ser anterior a la de inicio |

### Respuesta

`201 Created` si se ha abierto ahora, `200 OK` si esa empresa ya tenía un ejercicio con
ese nombre.

```json
{
  "id": 7,
  "empresa_contable_id": 3,
  "nombre": "2026",
  "fecha_inicio": "2026-01-01",
  "fecha_fin": "2026-12-31",
  "cerrado": false
}
```

El `nombre` es único dentro de la empresa y es la referencia que se manda en cada asiento
(`"ejercicio": "2026"`), nunca el `id`.

Repetir la llamada **no abre un segundo ejercicio ni toca el que hay**: no reabre uno
cerrado ni le mueve las fechas. Para eso hay que editarlo en la contabilidad.

---

## Dar de alta un tercero

Quien paga, antes de que exista ningún asiento: un propietario se da de alta como
`cliente` y la contabilidad devuelve su subcuenta.

```
POST /api/contabilidad/terceros
```

```json
{
  "empresa_contable_id": 3,
  "clase": "cliente",
  "nif": "12345678Z",
  "razon_social": "García Pérez, Antonio",
  "sujeto": { "tipo": "propietario", "id": "17" }
}
```

### Respuesta

```json
{ "cuenta": "43000001", "nombre": "García Pérez, Antonio" }
```

`201` si se ha dado de alta ahora, `200` si ese `sujeto` ya tenía subcuenta. **Repetir la
llamada no crea una segunda**: devuelve la que ya existía, así que puede reintentarse sin
miedo.

Esa cuenta es el identificador con el que luego se le nombra en los asientos, y la que
guarda tu sistema. `sujeto` es tu etiqueta opaca: la contabilidad la guarda y la compara,
pero no la interpreta. Las clases disponibles y cómo se numera están en
[Terceros y subcuentas](#terceros-y-subcuentas).

---

## La cuenta de un presupuesto o una derrama

El otro lado del asiento: el concepto por el que se cobra. El presupuesto anual va al
grupo de cuotas y **cada derrama tiene la suya**, para poder verlas por separado en el
mayor.

```
POST /api/contabilidad/cuentas-ingreso
```

```json
{
  "empresa_contable_id": 3,
  "clase": "derramas",
  "nombre": "Derrama grietas",
  "sujeto": { "tipo": "presupuesto", "id": "12" }
}
```

### Respuesta

```json
{ "cuenta": "75010001", "nombre": "Derrama grietas" }
```

| `clase` | Grupo | Denominación |
|---|---|---|
| `cuotas` | `7500` | Ingresos por cuotas de comunidad |
| `derramas` | `7501` | Ingresos por derramas |

Se numera igual que las subcuentas de tercero (4 de grupo + 4 de correlativo), aunque no
es un tercero: nadie debe nada aquí. `201` la primera vez y `200` las siguientes; el mismo
`sujeto` devuelve siempre la misma cuenta.

Con las dos altas hechas, el recibo ya se puede mandar: al debe la cuenta del propietario,
al haber la del presupuesto.

---

## La cuenta de un banco

Emitir el recibo es una cosa y cobrarlo otra: al cobrar, el dinero entra en una cuenta
corriente, y esa cuenta también necesita la suya en el plan (grupo `5720`, *Bancos*).

**No tiene endpoint HTTP**: solo se pide desde dentro, con el servicio.

```php
use App\Services\Contabilidad\ResolverCuentaTesoreriaService;

$cuenta = $tesoreria->banco(
    empresaContableId: 3,
    nombre: 'BANCO X C/C COMUNIDAD',
    sujetoTipo: 'cuenta_bancaria',
    sujetoId: '7',
);   // → 57200001
```

El `nombre` es cosa de quien llama: aquí lo escribe el administrador en los datos
financieros de la comunidad, porque es él quien decide cómo quiere leerlo en el mayor. Se
numera y se comporta igual que las cuentas de ingreso —mismo `sujeto` devuelve siempre la
misma cuenta, nunca una segunda—, y como ellas no es un tercero: nadie debe nada aquí, es
dónde está el dinero.

### Cambiar la denominación

```php
$renombrar->ejecutar(empresaContableId: 3, codigo: '57200001', nombre: 'BANCO Y C/C');
```

El nombre de una cuenta no forma parte de ningún asiento: el mayor se vuelve a sacar con
el de hoy y no altera ni un importe, así que cambiarlo es legítimo. El **código** no se
toca nunca una vez tiene movimientos. Devuelve `false` si esa empresa no tiene esa cuenta.

Quien llama desde fuera debe haber preguntado antes: en el plan manda quien lleva los
libros, y puede haber corregido allí la denominación a propósito.

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

Cómo se eligen los códigos de cuenta y qué dice el PGC al respecto está en
[docs/plan-de-cuentas.md](plan-de-cuentas.md).

---

## Errores

| Estado | Cuándo |
|---|---|
| `401` | Falta el token o no es válido |
| `403` | El token no es de esa empresa contable, o su dueño ya no tiene acceso a ella |
| `409` | El ejercicio está cerrado, o se han agotado las 9.999 subcuentas del grupo |
| `422` | El asiento no cuadra, una línea está mal formada, la fecha cae fuera del ejercicio, la cuenta no existe, el ejercicio no existe, o el tercero no existe y no se autorizó crearlo. En el alta de empresa, falta el CIF o el nombre. En la apertura de ejercicio, falta el nombre o las fechas están del revés |

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
| `EmpresaContableInvalidaException` | Alta de empresa sin CIF o sin nombre |
| `EjercicioContableInvalidoException` | Ejercicio sin nombre, con fecha fin anterior a la de inicio, o de una empresa que no existe |

---

## Carga inicial

Cuando un sistema ya tiene terceros de antes, hay que darles subcuenta una primera vez.
Ese bucle **vive en el lado de quien llama**, no en la contabilidad: la contabilidad no
puede recorrer una tabla que no conoce.

El procedimiento es mandar los asientos de arranque —o simplemente los primeros asientos
reales— con `crear_terceros_desconocidos` activado y los datos fiscales completos en cada
línea de tercero. A partir de ahí, cada alta nueva crea su subcuenta en el momento y la
bandera vuelve a sobrar.
