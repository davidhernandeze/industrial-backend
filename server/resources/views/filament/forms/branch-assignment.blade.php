<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $listStyle = 'border: 1px solid rgb(128 128 128 / 0.25); border-radius: 0.5rem; min-height: 9rem; max-height: 14rem; overflow-y: auto; padding: 0.25rem;';
        $rowStyle = 'display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.125rem 0.5rem; font-size: 0.875rem;';
        $headerStyle = 'display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.25rem; font-size: 0.875rem; font-weight: 500;';
        $emptyStyle = 'padding: 0.5rem; font-size: 0.875rem; opacity: 0.6;';
    @endphp

    <div
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            options: @js($getOptions()),
            get selected() {
                return (this.state ?? []).map(String)
            },
            get assigned() {
                return this.options.filter((option) => this.selected.includes(option.id))
            },
            get available() {
                return this.options.filter((option) => ! this.selected.includes(option.id))
            },
            add(id) {
                this.state = [...this.selected, id]
            },
            remove(id) {
                this.state = this.selected.filter((selected) => selected !== id)
            },
        }"
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: 1rem;"
    >
        <div>
            <div style="{{ $headerStyle }}">
                <span>{{ __('users.assignment.available') }}</span>
                <x-filament::link
                    tag="button"
                    type="button"
                    size="xs"
                    x-show="available.length > 0"
                    x-on:click="state = options.map((option) => option.id)"
                >
                    {{ __('users.assignment.add_all') }}
                </x-filament::link>
            </div>

            <div style="{{ $listStyle }}">
                <template x-for="option in available" :key="option.id">
                    <div style="{{ $rowStyle }}">
                        <span x-text="option.name"></span>
                        <x-filament::icon-button
                            icon="heroicon-m-arrow-right"
                            size="sm"
                            :label="__('users.assignment.add')"
                            x-on:click="add(option.id)"
                        />
                    </div>
                </template>

                <p x-show="available.length === 0" style="{{ $emptyStyle }}">{{ __('users.assignment.empty') }}</p>
            </div>
        </div>

        <div>
            <div style="{{ $headerStyle }}">
                <span>{{ __('users.assignment.assigned') }}</span>
                <x-filament::link
                    tag="button"
                    type="button"
                    size="xs"
                    color="gray"
                    x-show="assigned.length > 0"
                    x-on:click="state = []"
                >
                    {{ __('users.assignment.remove_all') }}
                </x-filament::link>
            </div>

            <div style="{{ $listStyle }}">
                <template x-for="option in assigned" :key="option.id">
                    <div style="{{ $rowStyle }}">
                        <x-filament::icon-button
                            icon="heroicon-m-arrow-left"
                            size="sm"
                            color="gray"
                            :label="__('users.assignment.remove')"
                            x-on:click="remove(option.id)"
                        />
                        <span x-text="option.name" style="flex: 1;"></span>
                    </div>
                </template>

                <p x-show="assigned.length === 0" style="{{ $emptyStyle }}">{{ __('users.assignment.empty') }}</p>
            </div>
        </div>
    </div>
</x-dynamic-component>
