<a {{ $attributes->merge(['class' => 'press inline-flex h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-4 text-center text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 bg-stone-900 text-white hover:bg-stone-700 focus-visible:ring-amber-500']) }}>
    {{ $slot }}
</a>
