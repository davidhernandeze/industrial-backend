<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_17rem] xl:items-start">
        <nav aria-label="{{ __('manual.contents') }}" class="xl:sticky xl:top-24 xl:order-last">
            <x-filament::section :heading="__('manual.contents')">
                <ul class="space-y-2 text-sm">
                    @foreach ($sections as $key => $section)
                        <li>
                            <a href="#{{ $key }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $section['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        </nav>

        <div class="flex min-w-0 flex-col gap-6">
            @foreach ($sections as $key => $section)
                <div id="{{ $key }}" class="scroll-mt-24">
                    <x-filament::section :heading="$section['title']">
                        @if (filled($section['roles'] ?? []))
                            <x-slot name="afterHeader">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($section['roles'] as $role)
                                        <x-filament::badge>{{ \App\Enums\Role::from($role)->getLabel() }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </x-slot>
                        @endif

                        <div class="flex flex-col gap-3 text-sm leading-6">
                            @if (filled($section['intro'] ?? null))
                                <p>{!! Str::inlineMarkdown($section['intro']) !!}</p>
                            @endif

                            @if (filled($section['steps'] ?? []))
                                <ol class="list-decimal space-y-2 ps-5">
                                    @foreach ($section['steps'] as $step)
                                        <li>{!! Str::inlineMarkdown($step) !!}</li>
                                    @endforeach
                                </ol>
                            @endif

                            @if (filled($section['items'] ?? []))
                                <ul class="list-disc space-y-2 ps-5">
                                    @foreach ($section['items'] as $item)
                                        <li>{!! Str::inlineMarkdown($item) !!}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (filled($section['note'] ?? null))
                                <p class="rounded-lg bg-primary-50 px-3 py-2 dark:bg-primary-400/10">
                                    <strong>{{ __('manual.note') }}:</strong>
                                    {!! Str::inlineMarkdown($section['note']) !!}
                                </p>
                            @endif
                        </div>
                    </x-filament::section>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
