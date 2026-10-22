<x-owner-layout title="Tambah Pengguna">
    <x-card class="max-w-xl p-6">
        <form novalidate method="POST" action="{{ route('owner.users.store') }}" class="space-y-6">
            @csrf

            @include('owner.users._form')

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="password" value="Kata sandi awal" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Ulangi kata sandi" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                </div>
            </div>
            <p class="-mt-3 text-xs text-stone-500">Minimal 8 karakter. Sampaikan langsung ke pengguna; mereka bisa memakainya untuk masuk.</p>

            <div class="flex items-center gap-3">
                <x-primary-button>Simpan</x-primary-button>
                <a href="{{ route('owner.users.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
