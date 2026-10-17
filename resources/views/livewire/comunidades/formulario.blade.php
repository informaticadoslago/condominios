<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="2xl">
    <x-slot name="title">
        {{ $formulario->comunidad?->exists ? __('Modificar comunidad') : __('Nueva comunidad') }}
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
                <button type="button" @click="pestaña = 'contacto'"
                    class="px-2 py-2 font-semibold border-b-2"
                    x-bind:class="pestaña === 'contacto' ? 'border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'border-transparent text-gray-500'">
                    {{ __('Contacto') }}
                </button>
                <button type="button" @click="pestaña = 'administrador'"
                    class="px-2 py-2 font-semibold border-b-2"
                    x-bind:class="pestaña === 'administrador' ? 'border-indigo-600 text-indigo-700 dark:text-indigo-300' : 'border-transparent text-gray-500'">
                    {{ __('Administrador') }}
                </button>
            </div>

            <div x-show="pestaña === 'generales'">
                <div class="mt-1">
                    <x-label for="com-nombre" :value="__('Nombre')" />
                    <x-input id="com-nombre" class="block mt-1 w-full mayusculas" type="text" wire:model="formulario.nombre"
                        forzar-may autofocus />
                    <x-input-error for="formulario.nombre" class="mt-2" />
                </div>
                <div class="mt-3">
                    <x-label for="com-cif" :value="__('CIF')" />
                    <x-input id="com-cif" class="block mt-1 w-full mayusculas" type="text" wire:model="formulario.cif"
                        forzar-may />
                    <x-input-error for="formulario.cif" class="mt-2" />
                </div>

                {{-- Dirección del edificio: sale en el membrete y en el destinatario de las cartas. --}}
                <div class="mt-3 flex w-full gap-4">
                    <div class="w-4/5">
                        <x-label for="com-direccion" :value="__('Dirección')" />
                        <x-input id="com-direccion" class="block mt-1 w-full mayusculas" type="text"
                            wire:model="formulario.direccion1" placeholder="{{ __('Calle, avenida...') }}" />
                        <x-input-error for="formulario.direccion1" class="mt-2" />
                    </div>
                    <div class="w-1/5">
                        <x-label for="com-numero" :value="__('Número')" />
                        <x-input id="com-numero" class="block mt-1 w-full mayusculas" type="text"
                            wire:model="formulario.numero" />
                        <x-input-error for="formulario.numero" class="mt-2" />
                    </div>
                </div>
                <div class="mt-3 flex w-full gap-4">
                    <div class="w-1/5">
                        <x-label for="com-codigo-postal" :value="__('Código postal')" />
                        <x-input id="com-codigo-postal" class="block mt-1 w-full" type="text" inputmode="numeric"
                            maxlength="5" wire:model.live.blur="formulario.codigo_postal" />
                        <x-input-error for="formulario.codigo_postal" class="mt-2" />
                    </div>
                    <div class="w-2/5">
                        <x-label for="com-provincia" :value="__('Provincia')" />
                        <x-select id="com-provincia" class="block mt-1 w-full mayusculas" wire:model.live="formulario.provincia_id">
                            <option value="">{{ __('--') }}</option>
                            @foreach ($provincias as $provincia)
                                <option value="{{ $provincia->id }}">{{ $provincia->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="formulario.provincia_id" class="mt-2" />
                    </div>
                    <div class="w-2/5">
                        <x-label for="com-municipio" :value="__('Municipio')" />
                        <x-select id="com-municipio" class="block mt-1 w-full mayusculas" wire:model="formulario.municipio_id"
                            :disabled="! $formulario->provincia_id">
                            <option value="">{{ __('--') }}</option>
                            @foreach ($municipios as $municipio)
                                <option value="{{ $municipio->id }}">{{ trim($municipio->nombre) }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="formulario.municipio_id" class="mt-2" />
                    </div>
                </div>
            </div>

            <div x-show="pestaña === 'contacto'" x-cloak>
                <div class="mt-1">
                    <x-label for="com-correo-contacto" :value="__('Correo de contacto')" />
                    <x-input id="com-correo-contacto" class="block mt-1 w-full" type="email"
                        wire:model="formulario.correo_contacto" />
                    <x-input-error for="formulario.correo_contacto" class="mt-2" />
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('Al que se pide escribir en los avisos por correo (cargo, transferencia, devolución) si algo no cuadra.') }}
                    </p>
                </div>
            </div>

            {{-- Sin mayúsculas forzadas: sale tal cual en las convocatorias. --}}
            <div x-show="pestaña === 'administrador'" x-cloak>
                <div class="mt-1">
                    <x-label for="com-administrador-nombre" :value="__('Nombre del administrador')" />
                    <x-input id="com-administrador-nombre" class="block mt-1 w-full" type="text"
                        wire:model="formulario.administrador_nombre" />
                    <x-input-error for="formulario.administrador_nombre" class="mt-2" />
                </div>
                <div class="mt-3">
                    <x-label for="com-administrador-oficina" :value="__('Oficina de reunión')" />
                    <x-input id="com-administrador-oficina" class="block mt-1 w-full" type="text"
                        wire:model="formulario.administrador_oficina_reunion"
                        placeholder="las Oficinas de Fincas Lar (Av. García Barbón, 22 – 1º)" />
                    <x-input-error for="formulario.administrador_oficina_reunion" class="mt-2" />
                    <p class="text-xs text-gray-500 mt-1">{{ __('Lo que va detrás de «en» en la convocatoria, con su artículo: «…en segunda, en las Oficinas de…, en la población de…».') }}</p>
                </div>
                <div class="mt-3">
                    <x-label for="com-administrador-localidad" :value="__('Localidad')" />
                    <x-input id="com-administrador-localidad" class="block mt-1 w-full" type="text"
                        wire:model="formulario.administrador_localidad" />
                    <x-input-error for="formulario.administrador_localidad" class="mt-2" />
                </div>
            </div>

            <div x-show="pestaña === 'financieros'" x-cloak>
                <p class="text-sm text-gray-500 mb-3">{{ __('Cuenta bancaria y datos de acreedor que pide el banco para generar la remesa de recibos.') }}</p>

                <div class="flex w-full gap-4">
                    <div class="w-2/3">
                        <x-label for="com-iban" :value="__('IBAN')" />
                        <x-input id="com-iban" class="block mt-1 w-full mayusculas" type="text"
                            wire:model="formulario.iban" />
                        <x-input-error for="formulario.iban" class="mt-2" />
                    </div>
                    <div class="w-1/3">
                        <x-label :value="__('Entidad bancaria')" />
                        <div class="mt-1">
                            <x-dosl.input-autocomplete
                                wire:model="formulario.entidad_bancaria_texto"
                                source="buscarEntidadesBancarias"
                                items="resultadosEntidadesBancarias"
                                valorCampo="valor"
                                etiquetaCampo="etiqueta"
                                valorModel="formulario.entidad_bancaria_id"
                                placeholder="{{ __('Código o nombre...') }}" />
                        </div>
                        <x-input-error for="formulario.entidad_bancaria_id" class="mt-2" />
                    </div>
                </div>

                <div class="mt-3">
                    <x-label for="com-nombre-contable" :value="__('Nombre contable de la cuenta')" />
                    <x-input id="com-nombre-contable" class="block mt-1 w-full mayusculas" type="text"
                        wire:model="formulario.nombre_contable" forzar-may />
                    <x-input-error for="formulario.nombre_contable" class="mt-2" />
                    <p class="text-xs text-gray-500 mt-1">{{ __('Con el que sale en el mayor. Solo hace falta si la comunidad lleva contabilidad.') }}</p>
                </div>

                <div class="mt-3 flex w-full gap-4">
                    <div class="w-1/5">
                        <x-label for="com-sufijo" :value="__('Sufijo')" />
                        <x-input id="com-sufijo" class="block mt-1 w-full" type="text" inputmode="numeric"
                            pattern="[0-9]{3}" maxlength="3" wire:model="formulario.sufijo" />
                        <x-input-error for="formulario.sufijo" class="mt-2" />
                    </div>
                    <div class="w-4/5">
                        <x-label for="com-acreedor" :value="__('Identificador de acreedor SEPA')" />
                        <x-input id="com-acreedor" class="block mt-1 w-full mayusculas" type="text"
                            wire:model="formulario.identificador_acreedor_sepa" placeholder="ES00000A00000000" />
                        <x-input-error for="formulario.identificador_acreedor_sepa" class="mt-2" />
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>
