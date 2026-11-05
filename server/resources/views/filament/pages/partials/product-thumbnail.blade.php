@if ($url)
    <img src="{{ $url }}" alt="" loading="lazy" class="size-10 shrink-0 rounded-md object-cover" />
@else
    <span data-pos-thumbnail-placeholder class="flex size-10 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-400 dark:bg-white/5">
        <x-filament::icon icon="heroicon-o-photo" class="size-5" />
    </span>
@endif
