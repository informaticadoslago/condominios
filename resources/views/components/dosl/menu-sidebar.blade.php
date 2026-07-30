@props(['menu'])

@php
    $user = auth()->user();
    $sidebarClass = $menu['class'] ?? 'bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700';
    $sidebarClass .= ' backdrop-blur-sm bg-zinc-50 dark:!bg-zinc-900';
@endphp

<flux:sidebar sticky collapsible class="{{ $sidebarClass }}">
    @foreach ($menu['content'] as $block)
        @if (isset($block['can']) && !$user?->can($block['can']))
            @continue
        @endif

        @switch($block['type'] ?? 'nav')
            @case('header')
                <flux:sidebar.header>
                    <flux:sidebar.brand href="#" logo="https://fluxui.dev/img/demo/logo.png"
                        logo:dark="https://fluxui.dev/img/demo/dark-mode-logo.png" name="{{ __($block['name'] ?? '') }}" />
                    {{-- <flux:sidebar.collapse
                        class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" /> --}}
                </flux:sidebar.header>
            @break

            @case('spacer')
                <flux:sidebar.spacer />
            @break

            @case('nav')
                <flux:sidebar.nav>
                    {{-- Acordeón: solo un grupo abierto a la vez (Flux cierra los demás al abrir uno). --}}
                    <ui-disclosure-group exclusive class="contents">
                    @foreach ($block['items'] ?? [] as $item)
                        @if (isset($item['can']) && !$user?->can($item['can']))
                            @continue
                        @endif

                        @if (($item['type'] ?? 'item') === 'group')
                            @php
                                // Abrir por defecto solo el grupo que contiene la ruta actual.
                                // Coincide por sección: 'alumnos.editar'/'alumnos.crear' mantienen
                                // abierto el grupo de 'alumnos.index' (subrutas de la misma sección).
                                $rutaActual  = request()->route()?->getName();
                                $grupoActivo = collect($item['items'] ?? [])->contains(function ($s) use ($rutaActual) {
                                    $ruta = $s['route'] ?? null;
                                    if (! $ruta || ! $rutaActual) {
                                        return false;
                                    }
                                    $seccion = \Illuminate\Support\Str::beforeLast($ruta, '.');
                                    return $rutaActual === $ruta
                                        || \Illuminate\Support\Str::startsWith($rutaActual, $seccion . '.');
                                });
                            @endphp
                            <flux:sidebar.group expandable :expanded="$grupoActivo" class="grid" heading="{{ __($item['label'] ?? '') }}" tooltip="{{ __($item['tooltip'] ?? '') }}">
                                
                            @if (!empty($item['icon']))
                                    <x-slot:icon>
                                        <i class="{{ $item['icon'] }}"></i>
                                    </x-slot:icon>
                                @endif
                                @foreach ($item['items'] ?? [] as $sub)
                                    @if (isset($sub['can']) && !$user?->can($sub['can']))
                                        @continue
                                    @endif
                                    @if (!empty($sub['disabled']))
                                        {{-- Deshabilitado: sin href ni wire:navigate para que Flux lo pinte como
                                             <button disabled> (inerte). Con href sería un <a> y el disabled no haría nada. --}}
                                        <flux:sidebar.item :disabled="true">
                                            @if (!empty($sub['icon']))
                                                <x-slot:icon>
                                                    <i class="{{ $sub['icon'] }}"></i>
                                                </x-slot:icon>
                                            @endif
                                            {{ __($sub['label'] ?? '') }}
                                        </flux:sidebar.item>
                                    @elseif (isset($sub['action']))
                                        {{-- Item de acción: no navega, dispara un evento Livewire (p.ej. abrir un modal). --}}
                                        <flux:sidebar.item href="#" x-on:click.prevent="Livewire.dispatch('{{ $sub['action'] }}')">
                                            @if (!empty($sub['icon']))
                                                <x-slot:icon>
                                                    <i class="{{ $sub['icon'] }}"></i>
                                                </x-slot:icon>
                                            @endif
                                            {{ __($sub['label'] ?? '') }}
                                        </flux:sidebar.item>
                                    @elseif (isset($sub['route']))
                                        <flux:sidebar.item href="{{ route($sub['route']) }}" wire:navigate>
                                            @if (!empty($sub['icon']))
                                                <x-slot:icon>
                                                    <i class="{{ $sub['icon'] }}"></i>
                                                </x-slot:icon>
                                            @endif
                                            {{ __($sub['label'] ?? '') }}
                                        </flux:sidebar.item>
                                    @elseif (isset($sub['href']))
                                        <flux:sidebar.item href="{{ $sub['href'] }}" wire:navigate>
                                            @if (!empty($sub['icon']))
                                                <x-slot:icon>
                                                    <i class="{{ $sub['icon'] }}"></i>
                                                </x-slot:icon>
                                            @endif
                                            {{ __($sub['label'] ?? '') }}
                                        </flux:sidebar.item>
                                    @endif
                                @endforeach
                            </flux:sidebar.group>                        
                        @elseif (isset($item['action']))
                            {{-- Item de acción: no navega, dispara un evento Livewire (p.ej. abrir un modal). --}}
                            <flux:sidebar.item href="#" x-on:click.prevent="Livewire.dispatch('{{ $item['action'] }}')">
                                @if (!empty($item['icon']))
                                    <x-slot:icon>
                                        <i class="{{ $item['icon'] }}"></i>
                                    </x-slot:icon>
                                @endif
                                {{ __($item['label'] ?? '') }}
                            </flux:sidebar.item>
                        @else

                            <flux:sidebar.item
                                href="{{ isset($item['route']) ? route($item['route']) : $item['href'] ?? '#' }}" wire:navigate>
                                @if (!empty($item['icon']))
                                    <x-slot:icon>
                                        <i class="{{ $item['icon'] }}"></i>
                                    </x-slot:icon>
                                @endif
                                {{ __($item['label'] ?? '') }}
                            </flux:sidebar.item>

                        @endif
                    @endforeach
                    </ui-disclosure-group>
                </flux:sidebar.nav>
            @break
        @endswitch
    @endforeach
</flux:sidebar>
