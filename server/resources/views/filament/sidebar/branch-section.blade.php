@php
    $currentBranch = app(\App\Support\CurrentBranch::class);
    $hasNoBranches = $currentBranch->available()->isEmpty();
    $items = \App\Filament\Navigation\SidebarNavigation::branchItems();
@endphp

@if ($hasNoBranches || $currentBranch->hasChoice() || filled($items))
    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgb(128 128 128 / 0.25);">
        @if ($hasNoBranches)
            <p class="fi-sidebar-group-label" style="padding: 0 0.5rem;">{{ __('branches.none_assigned') }}</p>
        @elseif ($currentBranch->hasChoice())
            <div style="padding: 0 0.5rem;">
                @include('filament.sidebar.branch-selector')
            </div>
        @endif

        @if (filled($items))
            <ul class="fi-sidebar-nav-groups" style="margin-top: 1rem;">
                <x-filament-panels::sidebar.group :collapsible="false" :items="$items" />
            </ul>
        @endif
    </div>
@endif
