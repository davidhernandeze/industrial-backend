<form method="POST" action="{{ route('filament.admin.current-branch') }}">
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
