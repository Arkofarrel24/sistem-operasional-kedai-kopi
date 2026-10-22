<x-owner-layout title="Tambah Menu">
    <x-card class="max-w-2xl p-6">
        <form novalidate method="POST" action="{{ route('owner.products.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            @include('owner.products._form')

            <div class="flex items-center gap-3">
                <x-primary-button>Simpan &amp; Atur Resep</x-primary-button>
                <a href="{{ route('owner.products.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
