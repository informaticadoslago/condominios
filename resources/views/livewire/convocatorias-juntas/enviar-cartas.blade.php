{{-- Enviar la convocatoria por correo. Para: una línea por inmueble, con casilla para dejarlo
     fuera, el ojo para ver la carta de ese inmueble (la que va adjunta) y "+" para CC/CCO.
     Debajo, asunto, texto y adjuntos, iguales para todos los correos. --}}
<x-dosl.dialog-modal wire:model.live="abrir" maxWidth="4xl">
    <x-slot name="title">
        {{ __('Enviar la convocatoria por correo') }}
    </x-slot>

    <x-slot name="content">
        <p class="text-sm">
            <span class="text-gray-500 mr-2">{{ __('De') }}</span>{{ config('mail.from.name') }} &lt;{{ config('mail.from.address') }}&gt;
        </p>

        <x-label class="mt-3" :value="__('Para')" />
        <div class="mt-1 max-h-64 overflow-y-auto border rounded-lg">
            <table class="table-striped w-full table-auto text-sm text-left">
                <thead class="font-medium border-b">
                    <tr>
                        <th class="py-2 px-3 w-px">
                            <input type="checkbox" wire:click="toggleTodos"
                                @checked($seleccion !== [] && count($seleccion) === collect($lineas)->where('con_correo', true)->count())
                                title="{{ __('Marcar/desmarcar todos') }}" />
                        </th>
                        <th class="py-2 px-3">{{ __('Inmueble') }}</th>
                        <th class="py-2 px-3">{{ __('Propietarios') }}</th>
                        <th class="py-2 px-3 w-px"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($lineas as $linea)
                        <tr wire:key="envio-convocatoria-{{ $linea['inmueble_id'] }}">
                            <td class="py-2 px-3 align-top">
                                {{-- Sin ningún correo no hay a quién mandarlo. --}}
                                <input type="checkbox" wire:model.live="seleccion" value="{{ $linea['inmueble_id'] }}"
                                    @disabled(! $linea['con_correo']) />
                            </td>
                            <td class="py-2 px-3 align-top whitespace-nowrap">{{ $linea['nomenclatura'] }}</td>
                            <td class="py-2 px-3">
                                @forelse ($linea['propietarios'] as $propietario)
                                    <div>
                                        <span class="mayusculas">{{ $propietario['nombre'] }}</span>
                                        @if ($propietario['correo'])
                                            <span class="text-gray-500 ml-1">{{ $propietario['correo'] }}</span>
                                            @if ($propietario['validado'])
                                                <i class="fa-solid fa-envelope text-green-600 ml-1" title="{{ __('Validado') }}"></i>
                                            @else
                                                <i class="fa-solid fa-envelope text-red-500 ml-1" title="{{ __('No validado') }}"></i>
                                            @endif
                                        @else
                                            <span class="text-gray-400 ml-1">{{ __('sin correo') }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-gray-400">{{ __('Sin propietarios') }}</span>
                                @endforelse
                            </td>
                            <td class="py-2 px-3 align-top whitespace-nowrap">
                                <a href="{{ route('convocatorias-juntas.carta-inmueble', [$convocatoriaId, $linea['inmueble_id']]) }}"
                                    target="_blank" class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                    title="{{ __('Ver la carta de este inmueble (la que va adjunta)') }}">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <button type="button" class="ml-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                    wire:click="toggleConCopia({{ $linea['inmueble_id'] }})"
                                    title="{{ __('Añadir CC/CCO') }}">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </td>
                        </tr>
                        @if (in_array($linea['inmueble_id'], $conCopia))
                            {{-- Con varios titulares la copia va solo en el primer correo del inmueble. --}}
                            <tr wire:key="envio-convocatoria-copia-{{ $linea['inmueble_id'] }}">
                                <td></td>
                                <td colspan="3" class="pb-2 px-3">
                                    <div class="flex gap-2">
                                        <div class="flex-1">
                                            <x-input class="block w-full text-xs" type="email" placeholder="CC"
                                                wire:model.blur="cc.{{ $linea['inmueble_id'] }}" />
                                            <x-input-error for="cc.{{ $linea['inmueble_id'] }}" class="mt-1" />
                                        </div>
                                        <div class="flex-1">
                                            <x-input class="block w-full text-xs" type="email" placeholder="CCO"
                                                wire:model.blur="cco.{{ $linea['inmueble_id'] }}" />
                                            <x-input-error for="cco.{{ $linea['inmueble_id'] }}" class="mt-1" />
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-1 text-sm font-medium">
            {{ __('Marcados') }}: {{ count($seleccion) }} {{ __('de') }} {{ count($lineas) }}
        </p>

        <div class="mt-3">
            <x-label for="envio-convocatoria-asunto" :value="__('Asunto')" />
            <x-input id="envio-convocatoria-asunto" class="block mt-1 w-full" type="text" maxlength="150" wire:model="asunto" />
            <x-input-error for="asunto" class="mt-2" />
        </div>

        {{-- Sin saludo: cada correo empieza por «Hola» y el nombre de su propietario. --}}
        <div class="mt-3">
            <x-label for="envio-convocatoria-texto" :value="__('Texto')" />
            <textarea id="envio-convocatoria-texto" wire:model="texto" rows="6"
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-2.5 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700 dark:border-slate-700 dark:bg-slate-800/50 dark:focus-visible:outline-blue-600"></textarea>
            <p class="text-xs text-gray-500">{{ __('Cada correo empieza con «Hola» y el nombre del propietario, y lleva debajo la fecha, las horas y su inmueble.') }}</p>
            <x-input-error for="texto" class="mt-2" />
        </div>

        {{-- Va lo marcado; desmarcar no borra nada. --}}
        <div class="mt-3">
            <x-label :value="__('Adjuntos')" />
            <div class="mt-1 border rounded-lg divide-y text-sm">
                <label class="flex items-center gap-2 px-3 py-2">
                    <input type="checkbox" wire:model.live="conCarta" />
                    <i class="fa-solid fa-file-pdf text-red-600"></i>
                    <span class="flex-1">
                        {{ __('Carta de la convocatoria') }}
                        <span class="text-xs text-gray-500">{{ __('la de su inmueble en cada correo') }}</span>
                    </span>
                    {{-- Con el modal cerrado (al cargar la lista) no hay convocatoria: sin id, route() fallaría y tumbaría la página. --}}
                    @if ($convocatoriaId)
                        <a href="{{ route('convocatorias-juntas.carta', $convocatoriaId) }}" target="_blank"
                            class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="{{ __('Ver la carta') }}">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    @endif
                </label>
                @foreach ($documentos as $documento)
                    <label wire:key="envio-convocatoria-documento-{{ $documento['id'] }}" class="flex items-center gap-2 px-3 py-2">
                        <input type="checkbox" wire:model.live="documentosMarcados" value="{{ $documento['id'] }}" />
                        <i class="fa-solid fa-paperclip text-gray-400"></i>
                        <span class="flex-1">
                            {{ $documento['nombre'] }}
                            <span class="text-xs text-gray-500">{{ $documento['tamano'] }}</span>
                        </span>
                        <a href="{{ route('documentos.ver', $documento['id']) }}" target="_blank"
                            class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="{{ __('Ver') }}">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </label>
                @endforeach
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-secondary-button type="button" wire:click="cerrar">
            {{ __('Cancelar') }}
        </x-secondary-button>
        <x-button type="button" class="ml-2" wire:click="enviar">
            {{ __('Enviar') }}
        </x-button>
    </x-slot>
</x-dosl.dialog-modal>
