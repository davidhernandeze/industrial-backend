<ul class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
    @foreach ($products as $product)
        <li wire:key="product-{{ $product['id'] }}">
            <button
                type="button"
                wire:click="addProduct({{ $product['id'] }})"
                x-on:mousedown.prevent
                x-on:click="term = ''"
                class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5"
            >
                @include('filament.pages.partials.product-thumbnail', ['url' => $product['picture']])
                <span class="min-w-0 flex-1 truncate">{{ $product['name'] }}</span>
                <span class="font-medium">{{ $product['price'] }}</span>
            </button>
        </li>
    @endforeach
</ul>
