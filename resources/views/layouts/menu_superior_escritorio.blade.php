        <flux:navbar class="hidden lg:flex scrollable">
            {{-- Ocultar / mostrar el menú lateral entero (hamburguesa ↔ 3 puntos). --}}
            <div x-data="{ colapsado: JSON.parse(localStorage.getItem('flux-sidebar-collapsed-desktop') || 'false') }"
                x-on:flux-sidebar-toggle.window="colapsado = ! colapsado">
                <button type="button" x-on:click="$dispatch('flux-sidebar-toggle')"
                    class="p-2 rounded-lg text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-800/5 dark:hover:bg-white/10"
                    aria-label="{{ __('Mostrar u ocultar el menú') }}" title="{{ __('Mostrar u ocultar el menú') }}">
                    <i class="fa-solid fa-bars" x-show="!colapsado"></i>
                    <i class="fa-solid fa-ellipsis-vertical" x-show="colapsado" x-cloak></i>
                </button>
            </div>
            <flux:spacer />
            @php $comunidadActual = session('comunidad_actual_id') ? \App\Models\Comunidad::find(session('comunidad_actual_id')) : null; @endphp
            @if ($comunidadActual)
                <flux:badge color="blue" class="mayusculas">
                    <i class="fa-solid fa-city mr-1"></i>{{ $comunidadActual->nombre }}
                </flux:badge>
                <flux:button href="{{ route('comunidad.salir') }}" icon="x-mark" variant="subtle"
                    title="{{ __('Salir de la comunidad') }}" aria-label="{{ __('Salir de la comunidad') }}" />
            @endif
            @if (config('flux.appearance.enabled'))
                    <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" x-show="!$flux.dark" icon="moon"
                        variant="subtle" aria-label="Toggle dark mode" />
                    <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" x-show="$flux.dark" icon="sun"
                        variant="subtle" aria-label="Toggle dark mode" />                
            @endif
            {{-- Perfil alineado a la derecha --}}
            <flux:dropdown align="end">
                <flux:profile initials="{{ Auth()->user()->persona->iniciales }}" />

                <flux:navmenu class="max-w-[12rem]">
                    <div class="px-2 py-1.5">
                        <flux:text size="sm">Signed in as</flux:text>
                        <flux:heading class="mt-1! truncate">{{ Auth()->user()->email }}</flux:heading>
                    </div>

                    <flux:navmenu.separator />
                    <flux:navmenu.item href="{{ route('profile.show') }}" icon="user" class="text-zinc-800 dark:text-white">Account
                    </flux:navmenu.item>

                    @canImpersonate
                        {{-- x-data hace falta aunque esté vacío: Alpine solo procesa directivas dentro
                             de un árbol con x-data, y el menú de Flux no lo lleva. --}}
                        <flux:navmenu.item href="#" icon="user-circle" class="text-zinc-800 dark:text-white" x-data
                            x-on:click.prevent="Livewire.dispatch('abrir-impersonar')">{{ __('Cambiar de identidad') }}
                        </flux:navmenu.item>
                    @endCanImpersonate

                    @impersonating
                        <flux:navmenu.item href="{{ route('impersonate.leave') }}" icon="arrow-uturn-left"
                            class="text-zinc-800 dark:text-white">{{ __('Volver a mi identidad') }}
                        </flux:navmenu.item>
                    @endImpersonating

                    <flux:navmenu.separator />

                    <x-dosl.logout-button label="Salir" />

                </flux:navmenu>
            </flux:dropdown>
        </flux:navbar>
