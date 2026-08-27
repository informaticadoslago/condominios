<div>
<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="2xl">
    <x-slot name="title">
        {{ $formulario->sociedad?->exists ? __('Modificar sociedad') : __('Nueva sociedad') }}
    </x-slot>

    <x-slot name="content">
        <div x-data="{ pestaña: 'generales' }">
            <div class="flex items-center gap-4 text-sm border-b mb-4">
                <button type="button" @click="pestaña = 'generales'"
                    class="px-2 py-2 font-semibold border-b-2"
                    x-bind:class="pestaña === 'generales' ? 'border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'border-transparent text-gray-500'">
                    {{ __('Datos generales') }}
                </button>
                <button type="button" @click="pestaña = 'financieros'"
                    class="px-2 py-2 font-semibold border-b-2"
                    x-bind:class="pestaña === 'financieros' ? 'border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'border-transparent text-gray-500'">
                    {{ __('Datos financieros') }}
                </button>
            </div>

            <div x-show="pestaña === 'generales'">
                <div class="mt-1">
                    <x-label for="soc-nombre" :value="__('Nombre')" />
                    <x-input id="soc-nombre" class="block mt-1 w-full mayusculas" type="text" wire:model="formulario.nombre"
                        forzar-may autofocus />
                    <x-input-error for="formulario.nombre" class="mt-2" />
                </div>
                <div class="mt-3">
                    <x-label for="soc-cif" :value="__('CIF')" />
                    <x-input id="soc-cif" class="block mt-1 w-full mayusculas" type="text" wire:model="formulario.cif"
                        forzar-may />
                    <x-input-error for="formulario.cif" class="mt-2" />
                </div>
            </div>

            <div x-show="pestaña === 'financieros'" x-cloak>
                <p class="text-sm text-gray-500 mb-3">{{ __('Cuentas bancarias de la sociedad. Puede añadir varias.') }}</p>

                @if (count($formulario->cuentas))
                    <div class="divide-y border rounded-lg">
                        @foreach ($formulario->cuentas as $i => $cuenta)
                            <div class="flex items-center justify-between gap-3 px-3 py-2"
                                wire:key="cuenta-{{ $cuenta['id'] ?? 'nueva-'.$i }}">
                                <div class="min-w-0">
                                    <div class="font-medium mayusculas truncate">
                                        {{ $cuenta['iban'] ? formatIbanSegments($cuenta['iban']) : __('(sin IBAN)') }}
                                        @if (! empty($cuenta['cuenta_contable']))
                                            <i class="fa-solid fa-link ml-1 text-green-600"
                                                title="{{ __('Enlazada con la contabilidad: :cuenta', ['cuenta' => $cuenta['cuenta_contable']]) }}"></i>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 truncate">
                                        {{ $cuenta['alias'] }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <x-button type="button" class="btn-editar" wire:click="editarCuenta({{ $i }})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </x-button>
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white"
                                        wire:click="quitarCuenta({{ $i }})" title="{{ __('Desactivar') }}">
                                        <i class="fa-solid fa-trash"> </i>
                                    </x-button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 italic">{{ __('Todavía no hay ninguna cuenta.') }}</p>
                @endif

                <x-button type="button" class="mt-3 btn btn-nuevo" wire:click="abrirNuevaCuenta" title="{{ __('Añadir cuenta') }}">
                    <i class="fa-solid fa-plus"> </i>{{ __('Añadir cuenta') }}
                </x-button>
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

{{-- Submodal por encima del anterior: alta/edición de UNA cuenta bancaria, con sitio
     de sobra para el IBAN (24 caracteres) en vez de encajarlo en una fila estrecha. --}}
<x-dosl.dialog-modal wire:model.live="abrirCuenta" maxWidth="md">
    <x-slot name="title">
        {{ $cuentaEditandoIndice !== null ? __('Modificar cuenta bancaria') : __('Nueva cuenta bancaria') }}
    </x-slot>

    <x-slot name="content">
        <div class="mt-1">
            <x-label for="cta-iban" :value="__('IBAN')" />
            <x-input id="cta-iban" class="block mt-1 w-full mayusculas" type="text" size="24" maxlength="34"
                wire:model="cuentaTemp.iban" autofocus />
            <x-input-error for="cuentaTemp.iban" class="mt-2" />
        </div>

        <div class="mt-3">
            <x-label :value="__('Entidad bancaria')" />
            <div class="mt-1">
                <x-dosl.input-autocomplete
                    wire:model="cuentaTemp.entidad_bancaria_texto"
                    source="buscarEntidadesBancarias"
                    items="resultadosEntidadesBancarias"
                    valorCampo="valor"
                    etiquetaCampo="etiqueta"
                    valorModel="cuentaTemp.entidad_bancaria_id"
                    placeholder="{{ __('Código o nombre...') }}" />
            </div>
            <x-input-error for="cuentaTemp.entidad_bancaria_id" class="mt-2" />
        </div>

        <div class="mt-3">
            <x-label for="cta-alias" :value="__('Alias')" />
            <x-input id="cta-alias" class="block mt-1 w-full mayusculas" type="text"
                wire:model="cuentaTemp.alias" forzar-may />
            <x-input-error for="cuentaTemp.alias" class="mt-2" />
        </div>

        <div class="mt-3">
            <x-label for="cta-nombre-contable" :value="__('Nombre contable de la cuenta')" />
            <x-input id="cta-nombre-contable" class="block mt-1 w-full mayusculas" type="text"
                wire:model="cuentaTemp.nombre_contable" forzar-may />
            <x-input-error for="cuentaTemp.nombre_contable" class="mt-2" />
            <p class="text-xs text-gray-500 mt-1">{{ __('Con el que sale en el mayor. Solo hace falta si la sociedad lleva contabilidad.') }}</p>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar accion="cerrarCuenta" />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardarCuenta"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
</div>
