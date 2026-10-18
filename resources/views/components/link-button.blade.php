<a {{ $attributes->merge(['class' => 'inline-flex items-center justify-center text-center gap-1 px-4 py-2 bg-stone-900 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-stone-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition']) }}>
    {{ $slot }}
</a>
