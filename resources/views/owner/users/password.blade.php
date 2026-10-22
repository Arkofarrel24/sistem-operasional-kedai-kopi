<x-owner-layout title="Reset Kata Sandi">
    <x-card class="mx-auto max-w-xl p-6">
        <p class="text-center text-sm text-stone-600">Buat kata sandi baru untuk <span class="font-medium text-stone-900">{{ $user->name }}</span> ({{ $user->email }}).</p>

        <form novalidate method="POST" action="{{ route('owner.users.password.update', $user) }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="password" value="Kata sandi baru" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autofocus autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Ulangi kata sandi" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                </div>
            </div>
            <p class="-mt-3 text-xs text-stone-500">Minimal 8 karakter. Perangkat yang memakai "Ingat saya" akan diminta masuk ulang.</p>

            <div class="flex items-center justify-center gap-3">
                <x-primary-button>Simpan kata sandi</x-primary-button>
                <a href="{{ route('owner.users.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
