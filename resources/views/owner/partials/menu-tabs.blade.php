<nav class="mb-4 flex justify-center gap-1 border-b border-stone-200" aria-label="Menu dan kategori">
    @foreach (['owner.products.index' => 'Daftar Menu', 'owner.categories.index' => 'Kategori'] as $route => $label)
        @php($active = request()->routeIs(str_replace('.index', '.*', $route)))
        <a href="{{ route($route) }}"
           @class([
               '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
               'border-amber-500 text-stone-900' => $active,
               'border-transparent text-stone-500 hover:text-stone-800' => ! $active,
           ])>{{ $label }}</a>
    @endforeach
</nav>
