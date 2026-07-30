{{-- resources/views/layouts/menu-flux-sidebar.blade.php --}}

@php
    $menu = config('sidebar');
    $user = auth()->user();
@endphp

<flux:sidebar sticky collapsible class="{{ $menu['class'] ?? 'bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700' }}">

    @foreach ($menu['content'] as $block)
        @if(isset($block['can']) && ! $user?->can($block['can']))
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
                    @foreach ($block['items'] as $item)
                        @if(isset($item['can']) && ! $user?->can($item['can']))
                            @continue
                        @endif

                        @if(($item['type'] ?? 'item') === 'group')
                            <flux:sidebar.group expandable class="grid"
                                icon="{{ $item['icon'] ?? '' }}" 
                                heading="{{ __($item['label'] ?? '') }}">
                                @foreach($item['items'] as $sub)
                                    @if(isset($sub['can']) && ! $user?->can($sub['can']))
                                        @continue
                                    @endif
                                    <flux:sidebar.item 
                                        icon="{{ $sub['icon'] ?? '' }}" 
                                        href="{{ $sub['href'] ?? '#' }}">
                                        {{ __($sub['label'] ?? '') }}
                                    </flux:sidebar.item>
                                @endforeach
                            </flux:sidebar.group>
                        @else
                            <flux:sidebar.item 
                                icon="{{ $item['icon'] ?? '' }}" 
                                href="{{ isset($item['route']) ? route($item['route']) : ($item['href'] ?? '#') }}">
                                {{ __($item['label'] ?? '') }}
                            </flux:sidebar.item>
                        @endif
                    @endforeach
                </flux:sidebar.nav>
                @break
        @endswitch
    @endforeach

</flux:sidebar>
