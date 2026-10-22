@props(['label' => 'Lainnya'])

{{--
    A small "more actions" menu for table rows. The panel is teleported to <body> and anchored to its
    button (Alpine anchor, bundled with Livewire), so scrolling tables never clip it. Esc closes it and
    returns focus to the button. Put plain <a> items in the slot.
--}}
<div x-data="{ open: false }" class="inline-block"
     x-on:keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }">
    <button type="button" x-ref="trigger" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true"
            {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-lg font-medium text-stone-700 hover:text-stone-900']) }}>
        {{ $label }}
        <svg class="h-4 w-4 transition-transform duration-150" x-bind:class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak x-anchor.bottom-end.offset.6="$refs.trigger"
             x-on:click.outside="if (! $refs.trigger.contains($event.target)) open = false"
             x-on:click="open = false"
             x-transition:enter="transition duration-150 ease-[cubic-bezier(0.23,1,0.32,1)]" x-transition:enter-start="opacity-0 scale-[0.97]"
             x-transition:leave="transition duration-100 ease-out" x-transition:leave-end="opacity-0"
             class="z-50 w-60 origin-top-right rounded-xl bg-white py-1 text-sm shadow-lg shadow-stone-900/10 ring-1 ring-stone-900/5 [&>a]:flex [&>a]:min-h-11 [&>a]:items-center [&>a]:px-4 [&>a]:text-stone-800 [&>a:hover]:bg-stone-50">
            {{ $slot }}
        </div>
    </template>
</div>
