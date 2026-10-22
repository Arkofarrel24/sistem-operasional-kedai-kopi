<x-owner-layout title="Tambah Kategori">
    <x-card class="max-w-xl p-6">
        <form novalidate method="POST" action="{{ route('owner.categories.store') }}" class="space-y-6">
            @csrf

            @include('owner.categories._form')

            <div class="flex items-center gap-3">
                <x-primary-button>Simpan</x-primary-button>
                <a href="{{ route('owner.categories.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
