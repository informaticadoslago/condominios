# Plan de cuentas

Todo lo que hay que saber sobre los códigos de cuenta de la aplicación: qué dice la ley, qué
decidimos nosotros y por qué, cómo se forma un código y cuál es la plantilla de arranque de
una comunidad.

## Índice

- [Qué manda la ley y qué no](#qué-manda-la-ley-y-qué-no)
- [Cómo se forma un código](#cómo-se-forma-un-código)
- [Los dos criterios](#los-dos-criterios)
- [La plantilla de comunidades](#la-plantilla-de-comunidades)
- [Remanente y resultado: subgrupo 12](#remanente-y-resultado-subgrupo-12)
- [Propietarios: la 430](#propietarios-la-430)
- [Ingresos: subgrupo 75](#ingresos-subgrupo-75)
- [Terceros y correlativos](#terceros-y-correlativos)
- [Fuente](#fuente)

---

## Qué manda la ley y qué no

El Plan General de Contabilidad ([RD 1514/2007](https://www.boe.es/buscar/act.php?id=BOE-A-2007-19884))
es de aplicación obligatoria, pero su **artículo 2** deja fuera justo lo que aquí interesa:

> No obstante lo dispuesto en el párrafo anterior, no tendrán carácter vinculante los
> movimientos contables incluidos en la quinta parte del Plan General de Contabilidad y los
> aspectos relativos a **numeración y denominación de cuentas incluidos en la cuarta parte**,
> excepto en aquellos aspectos que contengan criterios de registro o valoración.

Y la introducción del propio Plan (apartado 15) añade que la cuarta y la quinta parte son de
aplicación facultativa, aunque «es aconsejable que, en el caso de hacer uso de esta facultad,
se utilicen denominaciones similares».

O sea: el cuadro de cuentas no obliga, orienta. Lo que sí obliga son las cuentas anuales, y
por eso interesa que cada cuenta esté colgada del subgrupo que le corresponde: de ahí sale su
sitio en el balance y en la cuenta de pérdidas y ganancias.

El cuadro de la cuarta parte llega **hasta la cuenta de 3 dígitos** (con desarrollos
orientativos de 4 y 5 en algunos casos). El 4.º dígito en adelante es nuestro.

---

## Cómo se forma un código

Ocho dígitos, siempre: **4 de cuenta más 4 de subcuenta**.

```
7 5 0 1 0 0 0 0
│ │ │ │ └─┴─┴─┴── subcuenta: correlativo, nuestro
│ │ │ └────────── 4.º dígito: desarrollo libre, nuestro
└─┴─┴──────────── grupo, subgrupo y cuenta: PGC
```

De los ocho, solo los **tres primeros** salen del BOE.

---

## Los dos criterios

Por este orden:

1. **Si el uso estipulado encaja, se usa esa cuenta aunque le pongamos otro nombre.** El PGC
   define para qué sirve cada cuenta; el nombre no vincula (artículo 2). Una comunidad no
   tiene «clientes», pero la 430 es la cuenta de los deudores por la actividad ordinaria, y
   eso es exactamente un propietario.
2. **Si no encaja ninguna, se abre en un hueco no estipulado del subgrupo cuyo uso sí
   encaja**, y se desarrolla con el 4.º dígito.

Lo que no se hace nunca es ocupar un código cuyo uso el BOE ya destina a otra cosa, por
mucho que el número quede libre en nuestra base.

---

## La plantilla de comunidades

Vive en [`PlanCuentasComunidadesSeeder`](../database/seeders/PlanCuentasComunidadesSeeder.php),
como cuentas maestras (`empresa_contable_id` nulo) con las que arranca una empresa contable
nueva.

| Código | Nombre | Naturaleza |
|---|---|---|
| `12000000` | Remanente | Patrimonio neto |
| `12100000` | Resultados negativos de ejercicios anteriores | Patrimonio neto |
| `12900000` | Resultado del ejercicio | Patrimonio neto |
| `40000000` | Proveedores | Pasivo |
| `41000000` | Acreedores por prestaciones de servicios | Pasivo |
| `43000000` | Propietarios | Activo |
| `57200000` | Bancos | Activo |
| `62200000` | Reparación y conservación | Gasto |
| `62300000` | Servicios de profesionales independientes | Gasto |
| `62800000` | Suministros | Gasto |
| `62900000` | Servicios de limpieza | Gasto |
| `75000000` | Ingresos por cuotas de comunidad | Ingreso |
| `75010000` | Ingresos por derramas | Ingreso |

Es **una** plantilla, no el plan de cuentas del sistema. La contabilidad es genérica y no
sabe qué es una cuota ni una derrama: solo mueve céntimos entre códigos que le dan. El día
que entre otro tipo de empresa se añade otra plantilla al lado, sin tocar esta ni el motor.

De la tabla, tres cuentas llevan nombre nuestro sobre un uso del PGC: la `43000000`
(430 *Clientes*), la `62900000` (629 *Otros servicios*) y las dos de ingresos. El resto son
literales del cuadro.

---

## Remanente y resultado: subgrupo 12

El remanente es la **120**, del subgrupo 12 *«Resultados pendientes de aplicación»*. Estuvo
un tiempo en la `30000000`, que es grupo 3 *Existencias* y cuya 300 es *Mercaderías A*.

Están las tres del subgrupo porque el cierre las necesita: la **129** *Resultado del
ejercicio* es donde aterriza la diferencia entre las 75xx y las 62xx, y de ahí sale contra la
**120** o la **121** *Resultados negativos de ejercicios anteriores* según el signo.

---

## Propietarios: la 430

El propietario va a la **430 «Clientes»**, que en nuestro plan se llama `43000000`
**Propietarios**. Caso de manual del criterio 1: uso estipulado, nombre nuestro.

Se descartó la 431 para las derramas. En el BOE es *«Clientes, efectos comerciales a
cobrar»*, con 4310 en cartera, 4311 descontados, 4312 en gestión de cobro y 4315 impagados:
letras y pagarés aceptados. Aquí no hay efectos comerciales, así que el uso no encaja y no
vale ni renombrándola.

**La deuda no se desglosa por concepto.** El propietario debe una cantidad y tiene una sola
subcuenta. Cuánto se ha devengado por cuotas y cuánto por derramas lo dan las cuentas de
ingreso 7500 y 7501, que son de toda la comunidad y no bajan cuando alguien paga. El «este
propietario debe 180 € de derramas» es un dato de gestión y sale de los recibos, no de
interpretar saldos contables.

---

## Ingresos: subgrupo 75

El grupo 7 no tiene ninguna cuenta para las cuotas de una comunidad. De la 700 a la 709 son
ventas de mercaderías, de productos y prestaciones de servicios, y una comunidad ni vende ni
presta servicios: reparte gastos entre sus propietarios.

El sitio es el subgrupo **75, «Otros ingresos de gestión»**, cuyo uso sí encaja. Dentro, el
BOE estipula 751, 752, 753, 754, 755 y 759; el **750 está libre**. Las dos cuentas cuelgan de
ahí con el 4.º dígito:

| Código | Nombre |
|---|---|
| `75000000` | Ingresos por cuotas de comunidad |
| `75010000` | Ingresos por derramas |

Se descartó la 759 *«Ingresos por servicios diversos»*: su uso son servicios prestados a
terceros ajenos a la actividad, que no es el caso.

### Cuadro legal del grupo 7

Para el que venga detrás y quiera abrir otra cuenta de ingreso. Los subgrupos 72 y 78 no
existen.

| Subgrupo | Cuentas estipuladas |
|---|---|
| **70** Ventas de mercaderías, de producción propia, de servicios, etc. | 700, 701, 702, 703, 704, 705, 706, 708, 709 |
| **71** Variación de existencias | 710, 711, 712, 713 |
| **73** Trabajos realizados para la empresa | 730, 731, 732, 733 |
| **74** Subvenciones, donaciones y legados | 740, 746, 747 |
| **75** Otros ingresos de gestión | 751, 752, 753, 754, 755, 759 |
| **76** Ingresos financieros | 760, 761, 762, 763, 766, 767, 768, 769 |
| **77** Beneficios procedentes de activos no corrientes e ingresos excepcionales | 770, 771, 772, 773, 774, 775, 778 |
| **79** Excesos y aplicaciones de provisiones y de pérdidas por deterioro | 790 a 799 |

---

## Terceros y correlativos

Las cuentas de las que cuelgan los terceros no se eligen a mano: cada clase de tercero tiene
su `prefijo_cuenta` en la tabla `tipo_tercero_contables`, y esa cuenta de grupo es el prefijo
más `0000`.

| `clase` | Grupo | Denominación |
|---|---|---|
| `proveedor` | `4000` | Proveedores |
| `acreedor` | `4100` | Acreedores por prestaciones de servicios |
| `cliente` | `4300` | Clientes |
| `deudor` | `4400` | Deudores varios |

El correlativo lo pone [`ResolvedorCuentasService`](../app/Services/Contabilidad/ResolvedorCuentasService.php),
que es el único sitio del sistema que conoce esta regla: el primer cliente es el `43000001`,
el segundo el `43000002`. **Ese correlativo no tiene ninguna relación con el `id` del
tercero.** Al agotar las 9.999 subcuentas de un grupo se para y se avisa; no se salta al
grupo siguiente, para que un grupo siga siendo un prefijo único en informes y exportaciones.

Cómo se piden las altas desde fuera está en
[docs/api-contabilidad.md](api-contabilidad.md#terceros-y-subcuentas).

---

## Fuente

Real Decreto 1514/2007, de 16 de noviembre, por el que se aprueba el Plan General de
Contabilidad — [BOE-A-2007-19884](https://www.boe.es/buscar/act.php?id=BOE-A-2007-19884),
[texto consolidado en PDF](https://www.boe.es/buscar/pdf/2007/BOE-A-2007-19884-consolidado.pdf).

- Artículo 2: obligatoriedad del Plan y no vinculación de numeración y denominación.
- Introducción, apartado 15: carácter facultativo de las partes cuarta y quinta.
- Cuarta parte: cuadro de cuentas.
- Quinta parte: definiciones y relaciones contables, que es donde se lee para qué sirve
  realmente cada cuenta antes de decidir si encaja.

Todos los códigos citados en este documento están contrastados contra ese texto consolidado.
