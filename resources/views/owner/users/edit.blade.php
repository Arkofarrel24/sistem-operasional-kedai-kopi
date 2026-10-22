<x-owner-layout title="Ubah Pengguna">
    <x-card class="max-w-xl p-6">
        <form novalidate method="POST" action="{{ route('owner.users.update', $user) }}" class="space-y-6">
            @csrf
            @method('PUT')

            @include('owner.users._form')

            <div class="flex items-center gap-3">
                <x-primary-button>Simpan</x-primary-button>
                <a href="{{ route('owner.users.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
