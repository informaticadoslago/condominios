<x-botonera-page>
    <x-slot name="title">
        {{ __('Remesas') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Envíos de adeudos al banco') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" wire:click="abrirNueva" title="{{ __('Nueva') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nueva') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                <x-secondary-button type="button" wire:click="borrarFiltro" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
                @include('livewire.parciales.selector-columnas')
                <span class="ml-1 inline-block align-middle">
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <button type="button" title="{{ __('Acciones') }}"
                                class="p-2 rounded-lg text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-800/5 dark:hover:bg-white/10">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            {{-- Solo si está puesta ENVIAR_EMAIL_TRANSFERENCIAS. Aquí y no en
                                 cada remesa: los que pagan por transferencia no salen en
                                 ninguna remesa. --}}
                            @if ($avisoTransferenciaActivo)
                                <x-dropdown-link href="#" wire:click="abrirAvisoTransferencia">
                                    <i class="fa-solid fa-envelope mr-1"></i>{{ __('Avisar transferencias') }}
                                </x-dropdown-link>
                            @endif
                            <x-dropdown-link href="#" wire:click="abrirImportar">
                                <i class="fa-solid fa-file-import mr-1"></i>{{ __('Importar remesa') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </span>
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => 'Referencia'])
            </div>
            @include('livewire.parciales.filtros')

            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @if ($this->verColumna('referencia'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('referencia')">
                                    {{ __('Referencia') }}
                                    @if ($sort == 'referencia')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('fecha_cargo'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_cargo')">
                                    {{ __('Fecha de cargo') }}
                                    @if ($sort == 'fecha_cargo')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('cuenta'))
                                <th class="py-3 px-6">{{ __('Cuenta de abono') }}</th>
                            @endif
                            @if ($this->verColumna('recibos'))
                                <th class="py-3 px-6 text-right">{{ __('Recibos') }}</th>
                            @endif
                            @if ($this->verColumna('importe'))
                                <th class="py-3 px-6 text-right">{{ __('Importe') }}</th>
                            @endif
                            @if ($this->verColumna('devueltos'))
                                <th class="py-3 px-6 text-right">{{ __('Devueltos') }}</th>
                            @endif
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                @if ($this->verColumna('referencia'))
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->referencia }}</td>
                                @endif
                                @if ($this->verColumna('fecha_cargo'))
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->fecha_cargo?->format('d/m/Y') }}</td>
                                @endif
                                @if ($this->verColumna('cuenta'))
                                    <td class="px-6 py-4">{{ $item->cuentaBancaria?->iban }}</td>
                                @endif
                                @if ($this->verColumna('recibos'))
                                    <td class="px-6 py-4 text-right">{{ $item->lineas_count }}</td>
                                @endif
                                @if ($this->verColumna('importe'))
                                    <td class="px-6 py-4 text-right">
                                        {{ number_format((float) $item->lineas_sum_importe, 2, ',', '.') }}
                                    </td>
                                @endif
                                @if ($this->verColumna('devueltos'))
                                    <td class="px-6 py-4 text-right">
                                        @if ($item->lineas_devueltas_count)
                                            <span class="text-red-700 dark:text-red-400">{{ $item->lineas_devueltas_count }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 whitespace-nowrap">
                                    <x-button type="button" class="bg-gray-500 hover:bg-gray-600 text-white"
                                        wire:click="verDetalle({{ $item->id }})"
                                        title="{{ __('Ver los recibos de la remesa') }}">
                                        <i class="fa-solid fa-eye"> </i>
                                    </x-button>
                                    {{-- Enlace normal y no wire:navigate: es una descarga, no una página. --}}
                                    <a href="{{ route('remesas.fichero', $item) }}" class="btn-editar ml-1"
                                        title="{{ __('Descargar el fichero para el banco') }}">
                                        <i class="fa-solid fa-download"> </i>
                                    </a>
                                    {{-- Mientras quede algo en vuelo sin cobrar: dar por
                                         cobrado lo que el banco no devolvió, y marcar las
                                         devoluciones que van llegando por tandas. --}}
                                    @if ($item->lineas_pendientes_count)
                                        <x-button type="button" class="bg-green-600 hover:bg-green-700 text-white ml-1"
                                            wire:click="abrirCobro({{ $item->id }})"
                                            title="{{ __('Dar por cobrada') }}">
                                            <i class="fa-solid fa-hand-holding-dollar"> </i>
                                        </x-button>
                                    @endif
                                    {{-- Solo si está puesta ENVIAR_EMAIL_AL_ENVIAR_REMESA:
                                         el aviso nunca sale solo, hay que pulsarlo. --}}
                                    @if ($avisoRemesaActivo)
                                        <x-button type="button" class="bg-blue-600 hover:bg-blue-700 text-white ml-1"
                                            wire:click="avisarRemesa({{ $item->id }})"
                                            title="{{ __('Avisar por correo del cargo') }}">
                                            <i class="fa-solid fa-envelope"> </i>
                                        </x-button>
                                    @endif
                                    {{-- Devoluciones solo de lo ya cobrado: el banco no puede
                                         devolver un cargo que todavía no se ha dado por hecho. --}}
                                    @if ($item->lineas_devolvibles_count)
                                        <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                            wire:click="abrirDevolucion({{ $item->id }})"
                                            title="{{ __('Marcar devoluciones') }}">
                                            <i class="fa-solid fa-rotate-left"> </i>
                                        </x-button>
                                    @endif
                                    {{-- Solo mientras no haya ningún cobro: la remesa que se
                                         generó mal y no se llegó a mandar al banco. --}}
                                    @unless ($item->lineas_cobradas_count)
                                        <x-button type="button" class="bg-gray-600 hover:bg-gray-700 text-white ml-1"
                                            wire:click="confirmarDeshacer({{ $item->id }})"
                                            title="{{ __('Deshacer la remesa') }}">
                                            <i class="fa-solid fa-trash"> </i>
                                        </x-button>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($items->hasPages())
                    <div class="px-6 py-3">
                        {{ $items->links() }}
                    </div>
                @endif
            @else
                <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
            @endif
        </x-dosl.tabla>

        {{-- Nueva remesa en dos pasos: primero las fechas, y después el repaso de lo que
             va a entrar, para poder dejar fuera a alguno antes de presentarla. --}}
        <x-dosl.dialog-modal wire:model.live="nuevaAbierta" :maxWidth="$nuevaPaso === 1 ? 'lg' : '4xl'">
            <x-slot name="title">
                {{ $nuevaPaso === 1 ? __('Nueva remesa') : __('Recibos que van a entrar') }}
            </x-slot>

            <x-slot name="content">
                @if ($nuevaPaso === 1)
                    <div class="mb-4">
                        <x-label for="nuevaVencimiento">{{ __('Vencimiento que se cobra') }}:</x-label>
                        <x-input class="block mt-1 w-full" type="date" id="nuevaVencimiento" wire:model="nuevaVencimiento" />
                        <x-input-error for="nuevaVencimiento" class="mt-1" />
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Entran los recibos domiciliados de esa fecha que sigan pendientes y no estén ya presentados.') }}
                        </p>
                    </div>

                    <div>
                        <x-label for="nuevaFechaCargo">{{ __('Fecha de cargo') }}:</x-label>
                        <x-input class="block mt-1 w-full" type="date" id="nuevaFechaCargo" wire:model="nuevaFechaCargo" />
                        <x-input-error for="nuevaFechaCargo" class="mt-1" />
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('El día en que el banco carga los adeudos en la cuenta de cada propietario.') }}
                        </p>
                    </div>
                @else
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Vencimiento :vencimiento, con cargo el :cargo. Desmarca los que no quieras presentar.', [
                            'vencimiento' => \Carbon\Carbon::parse($nuevaVencimiento)->format('d/m/Y'),
                            'cargo'       => \Carbon\Carbon::parse($nuevaFechaCargo)->format('d/m/Y'),
                        ]) }}
                    </p>

                    <div class="max-h-96 overflow-y-auto border rounded-lg">
                        <table class="table-striped w-full table-auto text-sm text-left">
                            <thead class="font-medium border-b">
                                <tr>
                                    <th class="py-2 px-3 w-px"></th>
                                    <th class="py-2 px-3">{{ __('Inmueble') }}</th>
                                    <th class="py-2 px-3">{{ __('Propietario') }}</th>
                                    <th class="py-2 px-3">{{ __('Cuenta') }}</th>
                                    <th class="py-2 px-3 text-right">{{ __('Importe') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($recibosAremesar as $recibo)
                                    <tr wire:key="remesar-{{ $recibo->id }}">
                                        <td class="py-2 px-3">
                                            <input type="checkbox" wire:model.live="nuevaSeleccion" value="{{ $recibo->id }}" />
                                        </td>
                                        <td class="py-2 px-3 whitespace-nowrap">
                                            {{ $recibo->inmueble?->tipoInmueble?->descripcion }}
                                            {{ $recibo->inmueble?->planta }} {{ $recibo->inmueble?->puerta }}
                                        </td>
                                        <td class="py-2 px-3 mayusculas">{{ $recibo->propietario?->persona?->nombreCompleto }}</td>
                                        <td class="py-2 px-3">{{ $recibo->cuentaBancaria?->iban }}</td>
                                        <td class="py-2 px-3 text-right">{{ number_format((float) $recibo->saldo, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 font-medium">
                        {{ __('Marcados') }}: {{ count($nuevaSeleccion) }} {{ __('de') }} {{ count($recibosAremesar) }}
                        —
                        {{ number_format($recibosAremesar->whereIn('id', $nuevaSeleccion)->sum('saldo'), 2, ',', '.') }}
                    </p>
                @endif
            </x-slot>

            <x-slot name="footer">
                @if ($nuevaPaso === 1)
                    <x-secondary-button type="button" wire:click="$set('nuevaAbierta', false)">
                        {{ __('Cancelar') }}
                    </x-secondary-button>
                    <x-button type="button" class="ml-2" wire:click="repasarRemesa">
                        {{ __('Continuar') }}
                    </x-button>
                @else
                    <x-secondary-button type="button" wire:click="volverAFechas">
                        {{ __('Atrás') }}
                    </x-secondary-button>
                    <x-button type="button" class="ml-2" wire:click="generar">
                        {{ __('Generar remesa') }}
                    </x-button>
                @endif
            </x-slot>
        </x-dosl.dialog-modal>

        {{-- Dar por cobrada: lo que no volvió devuelto. --}}
        <x-dosl.dialog-modal wire:model.live="cobroAbierto" maxWidth="lg">
            <x-slot name="title">
                {{ __('Dar la remesa por cobrada') }}
            </x-slot>

            <x-slot name="content">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Se dan por cobrados todos los recibos de la remesa que no estén marcados como devueltos. Los que ya estén cobrados se quedan como están.') }}
                </p>

                <x-label for="cobroFecha">{{ __('Fecha del cobro') }}:</x-label>
                <x-input class="block mt-1 w-full" type="date" id="cobroFecha" wire:model="cobroFecha" />
                <x-input-error for="cobroFecha" class="mt-1" />
                <p class="mt-1 text-sm text-gray-500">
                    {{ __('Viene puesta la fecha de cargo de la remesa, que es cuando el banco movió el dinero.') }}
                </p>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button type="button" wire:click="$set('cobroAbierto', false)">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-button type="button" class="ml-2" wire:click="cobrarRemesa">
                    {{ __('Sí, dar por cobrada') }}
                </x-button>
            </x-slot>
        </x-dosl.dialog-modal>

        {{-- Devoluciones: solo lo que sigue en vuelo. Se abre tantas veces como tandas
             mande el banco. --}}
        <x-dosl.dialog-modal wire:model.live="devolucionAbierta" maxWidth="4xl">
            <x-slot name="title">
                {{ __('Marcar devoluciones') }}
            </x-slot>

            <x-slot name="content">
                <div class="flex gap-4 mb-4">
                    <div class="w-1/3">
                        <x-label for="devolucionFecha">{{ __('Fecha de la devolución') }}:</x-label>
                        <x-input class="block mt-1 w-full" type="date" id="devolucionFecha" wire:model="devolucionFecha" />
                        <x-input-error for="devolucionFecha" class="mt-1" />
                    </div>
                    <div class="flex-1">
                        <x-label for="devolucionMotivo">{{ __('Motivo') }}:</x-label>
                        <x-input class="block mt-1 w-full" type="text" id="devolucionMotivo"
                            wire:model="devolucionMotivo" placeholder="{{ __('Sin fondos, cuenta cancelada…') }}" />
                        <x-input-error for="devolucionMotivo" class="mt-1" />
                    </div>
                    <div class="w-1/5">
                        <x-label for="devolucionGastos">{{ __('Comisión por devolución') }}:</x-label>
                        <x-input class="block mt-1 w-full text-right" type="number" step="0.01" min="0"
                            id="devolucionGastos" wire:model="devolucionGastos" placeholder="0,00" />
                        <x-input-error for="devolucionGastos" class="mt-1" />
                    </div>
                </div>

                {{-- El fichero del banco solo marca las casillas; aplicar sigue siendo un
                     acto aparte, porque puede traer líneas de otra remesa. --}}
                <div class="flex items-end gap-3 mb-4 p-3 border rounded-lg bg-gray-50 dark:bg-zinc-800">
                    <div class="flex-1">
                        <x-label for="devolucionFichero">{{ __('Fichero de devoluciones del banco') }} (pain.002):</x-label>
                        <input class="block mt-1 w-full text-sm" type="file" id="devolucionFichero"
                            accept=".xml,text/xml,application/xml" wire:model="devolucionFichero" />
                        <x-input-error for="devolucionFichero" class="mt-1" />
                    </div>
                    <x-secondary-button type="button" wire:click="cargarFicheroDevoluciones"
                        wire:loading.attr="disabled" wire:target="devolucionFichero,cargarFicheroDevoluciones">
                        {{ __('Marcar las del fichero') }}
                    </x-secondary-button>
                </div>

                @if ($devolucionSinCasar)
                    <div class="mb-4 p-3 border border-amber-400 rounded-lg text-sm">
                        <p class="font-medium text-amber-700 dark:text-amber-400">
                            {{ __('Del fichero se han quedado fuera :count, por no ser de esta remesa o constar ya devueltas:', ['count' => count($devolucionSinCasar)]) }}
                        </p>
                        <ul class="mt-1 list-disc list-inside">
                            @foreach ($devolucionSinCasar as $suelta)
                                <li>
                                    <span class="mayusculas">{{ $suelta['deudor'] }}</span>
                                    — {{ number_format((float) $suelta['importe'], 2, ',', '.') }} €
                                    <span class="text-gray-500">({{ $suelta['referencia'] }})</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="max-h-96 overflow-y-auto border rounded-lg">
                    <table class="table-striped w-full table-auto text-sm text-left">
                        <thead class="font-medium border-b">
                            <tr>
                                <th class="py-2 px-3 w-px"></th>
                                <th class="py-2 px-3">{{ __('Inmueble') }}</th>
                                <th class="py-2 px-3">{{ __('Propietario') }}</th>
                                <th class="py-2 px-3">{{ __('Cuenta') }}</th>
                                <th class="py-2 px-3 text-right">{{ __('Importe') }}</th>
                                <th class="py-2 px-3">{{ __('Devuelto') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($lineasRemesa as $linea)
                                <tr wire:key="devolver-{{ $linea->id }}"
                                    @class(['opacity-60' => $linea->estaDevuelta()])>
                                    <td class="py-2 px-3">
                                        {{-- Las ya devueltas se ven, pero sin casilla: no se
                                             vuelven a marcar. Tampoco las que no llegaron a
                                             cobrarse, que no tienen nada que devolver. --}}
                                        @if (! $linea->estaDevuelta() && $linea->cobros_count)
                                            <input type="checkbox" wire:model.live="devolucionSeleccion" value="{{ $linea->id }}" />
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        {{ $linea->recibo?->inmueble?->tipoInmueble?->descripcion }}
                                        {{ $linea->recibo?->inmueble?->planta }} {{ $linea->recibo?->inmueble?->puerta }}
                                    </td>
                                    <td class="py-2 px-3 mayusculas">{{ $linea->recibo?->propietario?->persona?->nombreCompleto }}</td>
                                    <td class="py-2 px-3">{{ $linea->iban }}</td>
                                    <td class="py-2 px-3 text-right">{{ number_format((float) $linea->importe, 2, ',', '.') }}</td>
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        @if ($linea->estaDevuelta())
                                            <span class="text-red-700 dark:text-red-400">
                                                <i class="fa-solid fa-rotate-left"></i>
                                                {{ $linea->fecha_devolucion->format('d/m/Y') }}
                                            </span>
                                            @if ($linea->motivo_devolucion)
                                                <span class="text-gray-500">— {{ $linea->motivo_devolucion }}</span>
                                            @endif
                                            @if ((float) $linea->gastos_devolucion > 0)
                                                <span class="text-gray-500">
                                                    (+{{ number_format((float) $linea->gastos_devolucion, 2, ',', '.') }} €)
                                                </span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-input-error for="devolucionSeleccion" class="mt-2" />
                <p class="mt-3 font-medium">{{ __('Marcados') }}: {{ count($devolucionSeleccion) }}</p>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button type="button" wire:click="$set('devolucionAbierta', false)">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-button type="button" class="ml-2 bg-red-600 hover:bg-red-700" wire:click="marcarDevueltos">
                    {{ __('Marcar como devueltos') }}
                </x-button>
            </x-slot>
        </x-dosl.dialog-modal>

        {{-- Alta de una remesa ya presentada por otro programa: se sube el pain.008 real
             y se casan sus líneas con recibos nuestros por IBAN e importe, para poder
             casar más tarde su devolución por el EndToEndId original. --}}
        <x-dosl.dialog-modal wire:model.live="importarAbierto" maxWidth="4xl">
            <x-slot name="title">
                {{ __('Importar remesa') }}
            </x-slot>

            <x-slot name="content">
                <div class="flex items-end gap-3 mb-4 p-3 border rounded-lg bg-gray-50 dark:bg-zinc-800">
                    <div class="flex-1">
                        <x-label for="importarFichero">{{ __('Fichero de la remesa presentada') }} (pain.008):</x-label>
                        <input class="block mt-1 w-full text-sm" type="file" id="importarFichero"
                            accept=".xml,text/xml,application/xml" wire:model="importarFichero" />
                        <x-input-error for="importarFichero" class="mt-1" />
                    </div>
                    <x-secondary-button type="button" wire:click="analizarFichero"
                        wire:loading.attr="disabled" wire:target="importarFichero,analizarFichero">
                        {{ __('Analizar fichero') }}
                    </x-secondary-button>
                </div>

                @if ($importarAnalisis)
                    <div class="mb-4">
                        <x-label for="importarFechaCargo">{{ __('Fecha de cargo') }}:</x-label>
                        <x-input class="block mt-1 w-1/3" type="date" id="importarFechaCargo" wire:model="importarFechaCargo" />
                        <x-input-error for="importarFechaCargo" class="mt-1" />
                    </div>

                    @if ($importarAnalisis['yaImportadas'])
                        <div class="mb-4 p-3 border rounded-lg text-sm text-gray-500">
                            {{ __('Ya estaban importadas (mismo EndToEndId): :count', ['count' => count($importarAnalisis['yaImportadas'])]) }}
                        </div>
                    @endif

                    @if ($importarAnalisis['sinCasar'])
                        <div class="mb-4 p-3 border border-amber-400 rounded-lg text-sm">
                            <p class="font-medium text-amber-700 dark:text-amber-400">
                                {{ __('No se han podido casar con ningún recibo:') }}
                            </p>
                            <ul class="mt-1 list-disc list-inside">
                                @foreach ($importarAnalisis['sinCasar'] as $suelta)
                                    <li>
                                        <span class="mayusculas">{{ $suelta['deudor'] }}</span>
                                        — {{ number_format((float) $suelta['importe'], 2, ',', '.') }} €
                                        <span class="text-gray-500">({{ $suelta['motivo'] }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="max-h-96 overflow-y-auto border rounded-lg">
                        <table class="table-striped w-full table-auto text-sm text-left">
                            <thead class="font-medium border-b">
                                <tr>
                                    <th class="py-2 px-3 w-px"></th>
                                    <th class="py-2 px-3">{{ __('Inmueble') }}</th>
                                    <th class="py-2 px-3">{{ __('Deudor') }}</th>
                                    <th class="py-2 px-3">{{ __('IBAN') }}</th>
                                    <th class="py-2 px-3 text-right">{{ __('Importe') }}</th>
                                    <th class="py-2 px-3">{{ __('Estado del recibo') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($importarAnalisis['candidatas'] as $indice => $candidata)
                                    <tr wire:key="importar-{{ $indice }}">
                                        <td class="py-2 px-3">
                                            <input type="checkbox" wire:model.live="importarSeleccion" value="{{ $indice }}" />
                                        </td>
                                        <td class="py-2 px-3 whitespace-nowrap">{{ $candidata['inmueble'] }}</td>
                                        <td class="py-2 px-3 mayusculas">{{ $candidata['deudor'] }}</td>
                                        <td class="py-2 px-3">{{ $candidata['iban'] }}</td>
                                        <td class="py-2 px-3 text-right">{{ number_format((float) $candidata['importe'], 2, ',', '.') }}</td>
                                        <td class="py-2 px-3">
                                            @if ($candidata['estado'] === \App\Models\TipoEstadoRecibo::COBRADO)
                                                <span class="text-amber-600 dark:text-amber-400">{{ __('Ya cobrado') }}</span>
                                            @else
                                                {{ __('Generado') }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-input-error for="importarSeleccion" class="mt-2" />
                    <p class="mt-3 font-medium">{{ __('Marcados') }}: {{ count($importarSeleccion) }}</p>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button type="button" wire:click="$set('importarAbierto', false)">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                @if ($importarAnalisis && $importarAnalisis['candidatas'])
                    <x-button type="button" class="ml-2" wire:click="confirmarImportar">
                        {{ __('Importar remesa') }}
                    </x-button>
                @endif
            </x-slot>
        </x-dosl.dialog-modal>

        {{-- Lo que entró en la remesa. Solo lectura: una remesa presentada no se corrige,
             lo que venga después son devoluciones. --}}
        <x-dosl.dialog-modal wire:model.live="detalleAbierto" maxWidth="4xl">
            <x-slot name="title">
                {{ __('Recibos de la remesa') }} {{ $remesaDetalle?->referencia }}
            </x-slot>

            <x-slot name="content">
                @if (count($lineasDetalle))
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Cargo el :cargo — :count recibos, :importe', [
                            'cargo'   => $remesaDetalle?->fecha_cargo?->format('d/m/Y'),
                            'count'   => count($lineasDetalle),
                            'importe' => number_format((float) $lineasDetalle->sum('importe'), 2, ',', '.').' €',
                        ]) }}
                    </p>

                    <div class="max-h-96 overflow-y-auto border rounded-lg">
                        <table class="table-striped w-full table-auto text-sm text-left">
                            <thead class="font-medium border-b">
                                <tr>
                                    <th class="py-2 px-3">{{ __('Inmueble') }}</th>
                                    <th class="py-2 px-3">{{ __('Propietario') }}</th>
                                    <th class="py-2 px-3">{{ __('Cuenta') }}</th>
                                    <th class="py-2 px-3 text-right">{{ __('Importe') }}</th>
                                    <th class="py-2 px-3">{{ __('Situación') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($lineasDetalle as $linea)
                                    <tr wire:key="detalle-{{ $linea->id }}">
                                        <td class="py-2 px-3 whitespace-nowrap">
                                            {{ $linea->recibo?->inmueble?->tipoInmueble?->descripcion }}
                                            {{ $linea->recibo?->inmueble?->planta }} {{ $linea->recibo?->inmueble?->puerta }}
                                        </td>
                                        <td class="py-2 px-3 mayusculas">{{ $linea->recibo?->propietario?->persona?->nombreCompleto }}</td>
                                        <td class="py-2 px-3">{{ $linea->iban }}</td>
                                        <td class="py-2 px-3 text-right">{{ number_format((float) $linea->importe, 2, ',', '.') }}</td>
                                        <td class="py-2 px-3 whitespace-nowrap">
                                            @if ($linea->fecha_devolucion)
                                                <span class="text-red-600">
                                                    {{ __('Devuelto') }} {{ $linea->fecha_devolucion->format('d/m/Y') }}
                                                </span>
                                                @if ($linea->motivo_devolucion)
                                                    <div class="text-xs text-gray-500">{{ $linea->motivo_devolucion }}</div>
                                                @endif
                                            @elseif ($linea->cobros_count)
                                                <span class="text-green-600">{{ __('Cobrado') }}</span>
                                            @else
                                                <span class="text-gray-500">{{ __('Presentado') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500">{{ __('Esta remesa no tiene recibos.') }}</p>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button type="button" wire:click="$set('detalleAbierto', false)">
                    {{ __('Cerrar') }}
                </x-secondary-button>
            </x-slot>
        </x-dosl.dialog-modal>

        {{-- Aviso a los que pagan por transferencia. A diferencia de la remesa, aquí no
             hay nada que presentar al banco: solo se elige a quién se le recuerda que
             tiene que ingresar. Sale todo marcado y se desmarca lo que no toque. --}}
        <x-dosl.dialog-modal wire:model.live="avisoTransferenciaAbierto" maxWidth="4xl">
            <x-slot name="title">
                {{ __('Avisar a los que pagan por transferencia') }}
            </x-slot>

            <x-slot name="content">
                <div class="mb-3 w-64">
                    <x-label for="avisoVencimiento">{{ __('Vencimiento') }}:</x-label>
                    <x-input class="block mt-1 w-full" type="date" id="avisoVencimiento"
                        wire:model.live="avisoVencimiento" />
                </div>

                @if (count($recibosAavisar))
                    <div class="max-h-96 overflow-y-auto border rounded-lg">
                        <table class="table-striped w-full table-auto text-sm text-left">
                            <thead class="font-medium border-b">
                                <tr>
                                    <th class="py-2 px-3 w-px"></th>
                                    <th class="py-2 px-3">{{ __('Inmueble') }}</th>
                                    <th class="py-2 px-3">{{ __('Propietario') }}</th>
                                    <th class="py-2 px-3">{{ __('Correo') }}</th>
                                    <th class="py-2 px-3">{{ __('Último aviso') }}</th>
                                    <th class="py-2 px-3 text-right">{{ __('Pendiente') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($recibosAavisar as $recibo)
                                    @php($correoRecibo = $recibo->propietario?->correo())
                                    <tr wire:key="avisar-{{ $recibo->id }}">
                                        <td class="py-2 px-3">
                                            <input type="checkbox" wire:model.live="avisoSeleccion" value="{{ $recibo->id }}" />
                                        </td>
                                        <td class="py-2 px-3 whitespace-nowrap">
                                            {{ $recibo->inmueble?->planta }} {{ $recibo->inmueble?->puerta }}
                                        </td>
                                        <td class="py-2 px-3 mayusculas">{{ $recibo->propietario?->persona?->nombreCompleto }}</td>
                                        <td class="py-2 px-3">
                                            @if ($correoRecibo)
                                                {{ $correoRecibo->valor }}
                                            @else
                                                {{-- Sin dirección no se le puede avisar; se ve
                                                     aquí para no contarlo como enviado. --}}
                                                <span class="text-amber-600">{{ __('sin correo') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 whitespace-nowrap text-gray-500">
                                            {{ $recibo->avisos->first()?->enviado_at?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td class="py-2 px-3 text-right">{{ number_format((float) $recibo->saldo, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 font-medium">
                        {{ __('Marcados') }}: {{ count($avisoSeleccion) }} {{ __('de') }} {{ count($recibosAavisar) }}
                    </p>
                @else
                    <p class="text-sm text-gray-500">
                        {{ __('No hay recibos por transferencia pendientes con ese vencimiento.') }}
                    </p>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button type="button" wire:click="$set('avisoTransferenciaAbierto', false)">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-button type="button" class="ml-2" wire:click="enviarAvisosTransferencia">
                    {{ __('Enviar avisos') }}
                </x-button>
            </x-slot>
        </x-dosl.dialog-modal>
    </x-slot>
</x-botonera-page>
