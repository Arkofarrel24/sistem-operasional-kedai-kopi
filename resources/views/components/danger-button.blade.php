<button {{ $attributes->merge(['type' => 'submit', 'class' => 'press inline-flex h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-4 text-center text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 bg-red-600 text-white hover:bg-red-500 focus-visible:ring-red-500 disabled:opacity-40']) }}>
    {{ $slot }}
</button>
