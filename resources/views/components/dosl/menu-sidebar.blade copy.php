@props(['menu'])

@php
    $user = auth()->user();
    $sidebarClass = $menu['class'] ?? 'bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700';
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
                    <flux:sidebar.collapse
                        class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
                </flux:sidebar.header>
            @break

            @case('spacer')
                <flux:sidebar.spacer />
            @break

            @case('nav')
                <flux:sidebar.nav>
                    @foreach ($block['items'] ?? [] as $item)
                        @if (isset($item['can']) && !$user?->can($item['can']))
                            @continue
                        @endif

                        @if (($item['type'] ?? 'item') === 'group')
                            <flux:sidebar.group expandable class="grid" heading="{{ __($item['label'] ?? '') }}">
                                @if (!empty($item['icon']))
                                    <x-slot:icon>
                                        <i class="{{ $item['icon'] }}"></i>
                                    </x-slot:icon>
                                @endif
                                @foreach ($item['items'] ?? [] as $sub)
                                    @if (isset($sub['can']) && !$user?->can($sub['can']))
                                        @continue
                                    @endif
                                    @if (isset($sub['route']))
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
                        @else
                            <flux:sidebar.item
                                href="{{ isset($item['route']) ? route($item['route']) : $item['href'] ?? '#' }}">
                                @if (!empty($item['icon']))
                                    <x-slot:icon>
                                        <i class="{{ $item['icon'] }}"></i>
                                    </x-slot:icon>
                                @endif
                                {{ __($item['label'] ?? '') }}
                            </flux:sidebar.item>
                        @endif
                    @endforeach
                </flux:sidebar.nav>
            @break
        @endswitch
    @endforeach
</flux:sidebar>
