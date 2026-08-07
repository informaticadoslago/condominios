<x-botonera-page>
    <x-slot name="title">
        {{ __('Tokens de API') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Llaves para entrar por la API de contabilidad, una por empresa') }}
    </x-slot>
    <x-slot name="content">
        {{-- El token recién creado. Solo se ve aquí y solo esta vez. --}}
        @if ($tokenNuevo)
            {{-- copiar(): clipboard moderno cuando el navegador lo deja (hace falta https),
                 y si no, el textarea de toda la vida. --}}
            <div class="mb-4 rounded border border-amber-400 bg-amber-50 dark:bg-amber-900/30 px-4 py-3"
                x-data="{
                    copiado: false,
                    copiar(texto) {
                        const hecho = () => { this.copiado = true; setTimeout(() => this.copiado = false, 2000) };
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(texto).then(hecho);
                            return;
                        }
                        const area = document.createElement('textarea');
                        area.value = texto;
                        area.style.position = 'fixed';
                        area.style.opacity = 0;
                        document.body.appendChild(area);
                        area.select();
                        document.execCommand('copy');
                        document.body.removeChild(area);
                        hecho();
                    }
                }">
                <p class="font-medium text-amber-800 dark:text-amber-200">
                    {{ __('Copie el token ahora: no se vuelve a mostrar.') }}
                    <span x-show="copiado" x-transition class="ml-2 text-sm text-green-700 dark:text-green-400">
                        {{ __('Copiado') }}
                    </span>
                </p>
                <p class="mt-2 font-mono text-xs break-all text-gray-900 dark:text-gray-100 cursor-pointer hover:bg-amber-100 dark:hover:bg-amber-800/40 rounded px-1 py-0.5"
                    title="{{ __('Pulse para copiar') }}" x-on:click="copiar(@js($tokenNuevo))">
                    {{ $tokenNuevo }}
                    <i class="fa-regular fa-copy ml-1 text-gray-500"></i>
                </p>
                <button type="button" class="btn mt-2" wire:click="olvidarToken">{{ __('Ya lo he copiado') }}</button>
            </div>
        @endif

        <x-dosl.tabla>
            {{-- Alta: la empresa sale del rol de este usuario, no de la lista entera. --}}
            <div class="py-3 px-6 flex flex-wrap items-end gap-4 border-b">
                <div>
                    <x-label for="ta-empresa" :value="__('Empresa contable')" />
                    <x-select id="ta-empresa" class="block mt-1 w-full py-3 text-sm h-auto px-3"
                        wire:model="empresa_contable_id">
                        <option value="">{{ __('Elija una') }}</option>
                        @foreach ($empresas as $empresa)
                            <option value="{{ $empresa->id }}">{{ $empresa->razon_social }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="empresa_contable_id" class="mt-2" />
                </div>
                <div>
                    <x-label for="ta-nombre" :value="__('Nombre (opcional)')" />
                    <x-input id="ta-nombre" class="block mt-1" type="text" wire:model="nombre"
                        placeholder="{{ __('Para reconocerlo después') }}" />
                    <x-input-error for="nombre" class="mt-2" />
                </div>
                <div>
                    <x-label for="ta-escribir" :value="__('Puede')" />
                    <x-select id="ta-escribir" class="block mt-1 py-3 text-sm h-auto px-3" wire:model="escribir">
                        <option value="1">{{ __('Leer y escribir') }}</option>
                        <option value="0">{{ __('Solo leer') }}</option>
                    </x-select>
                </div>
                <button type="button" class="btn btn-nuevo" wire:click="crear" title="{{ __('Crear token') }}">
                    <i class="fa-solid fa-plus"> </i>{{ __('Crear token') }}
                </button>
            </div>

            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Nombre') }}</th>
                            <th class="py-3 px-6">{{ __('Empresa contable') }}</th>
                            <th class="py-3 px-6">{{ __('Puede') }}</th>
                            <th class="py-3 px-6">{{ __('Creado') }}</th>
                            <th class="py-3 px-6">{{ __('Último uso') }}</th>
                            <th class="py-3 px-6">{{ __('Caduca') }}</th>
                            <th class="py-3 px-6"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">{{ $item->name }}</td>
                                <td class="px-6 py-4">
                                    @if ($item->empresaContable)
                                        {{ $item->empresaContable }}
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">
                                            {{ __('Sin empresa (token antiguo)') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    {{ $item->escribe ? __('Leer y escribir') : __('Solo leer') }}
                                </td>
                                <td class="px-6 py-4">{{ $item->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4">
                                    {{ $item->last_used_at?->format('d/m/Y H:i') ?? __('Nunca') }}
                                </td>
                                <td class="px-6 py-4">
                                    @if (! $item->expires_at)
                                        {{ __('No caduca') }}
                                    @elseif ($item->expires_at->isPast())
                                        <span class="text-red-600 dark:text-red-400">
                                            {{ __('Caducado') }} ({{ $item->expires_at->format('d/m/Y') }})
                                        </span>
                                    @else
                                        {{ $item->expires_at->format('d/m/Y') }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button type="button" class="btn btn-borrar" wire:click="revocar({{ $item->id }})"
                                        wire:confirm="{{ __('¿Revocar este token? Quien lo esté usando dejará de entrar.') }}"
                                        title="{{ __('Revocar') }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="py-3 px-6">{{ __('Todavía no ha creado ningún token.') }}</div>
            @endif
        </x-dosl.tabla>
    </x-slot>
</x-botonera-page>
