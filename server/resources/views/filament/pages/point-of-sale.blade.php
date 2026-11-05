<x-filament-panels::page>
    <div
        x-data="{
            term: '',
            timer: null,
            focusScan() {
                this.$refs.scan.focus()
            },
            submit() {
                clearTimeout(this.timer)
                const code = this.term.trim()
                this.term = ''
                if (code !== '') {
                    $wire.scan(code)
                }
            },
            typed() {
                clearTimeout(this.timer)
                this.timer = setTimeout(() => $wire.search(this.term), 250)
            },
            reset() {
                clearTimeout(this.timer)
                this.term = ''
                $wire.search('')
            },
            press(key) {
                this.term += key
                this.typed()
            },
            backspace() {
                this.term = this.term.slice(0, -1)
                this.typed()
            },
        }"
        x-init="$nextTick(() => focusScan())"
        x-on:pos-focus-scan.window="$nextTick(() => focusScan())"
        x-on:pos-focus-quantity.window="
            const id = $event.detail.id
            setTimeout(() => {
                const input = document.getElementById('pos-quantity-' + id)
                input?.focus()
                input?.select()
            }, 100)
        "
        class="grid gap-6 lg:grid-cols-3"
    >
        <div class="flex flex-col gap-4 lg:col-span-2">
            <div class="relative" wire:ignore>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        x-ref="scan"
                        x-model="term"
                        x-on:input="typed()"
                        x-on:keydown.enter.prevent="submit()"
                        x-on:keydown.escape="reset()"
                        x-on:blur="setTimeout(() => { if (document.activeElement === document.body) { focusScan() } }, 150)"
                        autocomplete="off"
                        :placeholder="__('point_of_sale.scan_placeholder')"
                    />
                </x-filament::input.wrapper>
            </div>

            @if ($results !== [])
                <ul class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-gray-900">
                    @foreach ($results as $result)
                        <li wire:key="result-{{ $result['id'] }}">
                            <button
                                type="button"
                                wire:click="addProduct({{ $result['id'] }})"
                                x-on:click="term = ''"
                                class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                            >
                                <span>{{ $result['name'] }}</span>
                                <span class="font-medium">{{ $result['price'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                @if ($items->isEmpty())
                    <p class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">{{ __('point_of_sale.empty') }}</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('sales.fields.product') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('sales.fields.quantity') }}</th>
                                <th class="w-28 px-4 py-3 text-right font-medium">{{ __('sales.fields.unit_price') }}</th>
                                <th class="w-28 px-4 py-3 text-right font-medium">{{ __('sales.fields.line_total') }}</th>
                                <th class="w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($items as $item)
                                <tr wire:key="line-{{ $item->id }}">
                                    <td class="px-4 py-3">{{ $item->product->name }}</td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center gap-2">
                                            <x-filament::icon-button
                                                icon="heroicon-m-minus"
                                                color="gray"
                                                :label="__('point_of_sale.decrease')"
                                                wire:click="changeQuantity({{ $item->id }}, -1)"
                                            />
                                            <input
                                                wire:key="quantity-{{ $item->id }}-{{ $item->cart_quantity }}"
                                                id="pos-quantity-{{ $item->id }}"
                                                type="number"
                                                min="0"
                                                step="{{ $item->unit->allowsFractions() ? '0.001' : '1' }}"
                                                value="{{ $item->unit->allowsFractions() ? rtrim(rtrim($item->cart_quantity, '0'), '.') : (int) $item->cart_quantity }}"
                                                wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                                x-on:keydown.enter.prevent="$el.blur(); focusScan()"
                                                aria-label="{{ __('sales.fields.quantity') }}: {{ $item->product->name }}"
                                                class="w-20 rounded-md border border-gray-300 bg-transparent px-2 py-1 text-right dark:border-white/20"
                                            />
                                            <x-filament::icon-button
                                                icon="heroicon-m-plus"
                                                color="gray"
                                                :label="__('point_of_sale.increase')"
                                                wire:click="changeQuantity({{ $item->id }}, 1)"
                                            />
                                            <span class="text-gray-500 dark:text-gray-400">{{ __('branch_products.unit_abbreviations.'.$item->unit->value) }}</span>

                                            @if ($item->unit->commonQuantities() !== [])
                                                <select
                                                    wire:key="weights-{{ $item->id }}-{{ $item->cart_quantity }}"
                                                    wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                                    aria-label="{{ __('point_of_sale.weight') }}: {{ $item->product->name }}"
                                                    class="rounded-md border border-gray-300 bg-transparent px-2 py-1 text-sm dark:border-white/20 dark:bg-gray-900"
                                                >
                                                    <option value="" disabled @selected(! array_key_exists(rtrim(rtrim($item->cart_quantity, '0'), '.'), $item->unit->commonQuantities()))>{{ __('point_of_sale.weight') }}</option>
                                                    @foreach ($item->unit->commonQuantities() as $quantity => $label)
                                                        <option value="{{ $quantity }}" @selected((string) $quantity === rtrim(rtrim($item->cart_quantity, '0'), '.'))>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ \Illuminate\Support\Number::currency((float) $item->price, 'MXN', 'es_MX') }}</td>
                                    <td class="px-4 py-3 text-right font-medium">{{ \Illuminate\Support\Number::currency((float) $item->cart_total, 'MXN', 'es_MX') }}</td>
                                    <td class="px-2 py-2 text-right">
                                        <x-filament::icon-button
                                            icon="heroicon-m-x-mark"
                                            color="gray"
                                            :label="__('point_of_sale.remove')"
                                            wire:click="removeLine({{ $item->id }})"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <aside class="flex flex-col gap-4 lg:sticky lg:top-24 lg:self-start">
            @if ($currentBranch->hasChoice())
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    @include('filament.sidebar.branch-selector')
                </div>
            @endif

            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('sales.fields.total') }}</p>
                <p class="mt-1 text-5xl font-bold tracking-tight">{{ $total }}</p>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ trans_choice('point_of_sale.lines', $items->count(), ['count' => $items->count()]) }}</p>
            </div>

            <div class="flex flex-col gap-2">
                {{ $this->checkoutAction }}
                {{ $this->clearAction }}
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('point_of_sale.shortcut_hint') }}</p>

            <div wire:ignore class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                @php
                    $keyClass = 'rounded-md border border-gray-200 bg-gray-50 py-3 text-sm font-medium hover:bg-gray-100 active:bg-gray-200 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10 dark:active:bg-white/20';
                @endphp

                <div class="flex flex-col gap-1">
                    @foreach (['1234567890', 'qwertyuiop', 'asdfghjklñ', 'zxcvbnm'] as $row)
                        <div class="grid grid-cols-10 gap-1">
                            @foreach (mb_str_split($row) as $key)
                                <button type="button" class="{{ $keyClass }}" x-on:mousedown.prevent="press(@js($key))">{{ mb_strtoupper($key) }}</button>
                            @endforeach

                            @if ($row === 'zxcvbnm')
                                <button type="button" class="{{ $keyClass }} col-span-3" x-on:mousedown.prevent="backspace()">{{ __('point_of_sale.keyboard.backspace') }}</button>
                            @endif
                        </div>
                    @endforeach

                    <div class="grid grid-cols-10 gap-1">
                        <button type="button" class="{{ $keyClass }} col-span-3" x-on:mousedown.prevent="reset()">{{ __('point_of_sale.keyboard.clear') }}</button>
                        <button type="button" class="{{ $keyClass }} col-span-4" x-on:mousedown.prevent="press(' ')">{{ __('point_of_sale.keyboard.space') }}</button>
                        <button type="button" class="{{ $keyClass }} col-span-3" x-on:mousedown.prevent="submit()">{{ __('point_of_sale.keyboard.search') }}</button>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</x-filament-panels::page>
