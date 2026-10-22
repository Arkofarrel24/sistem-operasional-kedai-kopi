<button {{ $attributes->merge(['type' => 'button', 'class' => 'press inline-flex h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-4 text-center text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 bg-white text-stone-800 ring-1 ring-inset ring-stone-300 hover:bg-stone-50 focus-visible:ring-amber-500 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
