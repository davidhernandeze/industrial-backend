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
            <form method="POST" action="{{ route('filament.admin.current-branch') }}" style="padding: 0 0.5rem;">
                @csrf

                <label for="current-branch" class="fi-sidebar-group-label" style="display: block; margin-bottom: 0.5rem;">
                    {{ __('branches.current') }}
                </label>

                <x-filament::input.wrapper>
                    <x-filament::input.select id="current-branch" name="branch_id" onchange="this.form.submit()">
                        @foreach ($currentBranch->available() as $branch)
                            <option value="{{ $branch->getKey() }}" @selected($branch->is($currentBranch->get()))>{{ $branch->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </form>
        @endif

        @if (filled($items))
            <ul class="fi-sidebar-nav-groups" style="margin-top: 1rem;">
                <x-filament-panels::sidebar.group :collapsible="false" :items="$items" />
            </ul>
        @endif
    </div>
@endif
