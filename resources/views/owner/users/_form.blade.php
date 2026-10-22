@php($isMe = $user->exists && $user->is(auth()->user()))

<div>
    <x-input-label for="name" value="Nama" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="off" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div>
    <x-input-label for="email" value="Email" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="off" />
    <x-input-error :messages="$errors->get('email')" class="mt-2" />
</div>

<div>
    <x-input-label for="role" value="Peran" />
    @if ($isMe)
        {{-- Owners cannot demote themselves; the server enforces this too. --}}
        <input type="hidden" name="role" value="{{ $user->role->value }}">
        <p class="mt-1 text-sm">{{ $user->role->label() }} <span class="text-stone-500">(peran akun Anda sendiri tidak bisa diubah)</span></p>
    @else
        <x-select-input id="role" name="role" class="mt-1 block w-full sm:w-56" required>
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </x-select-input>
        <p class="mt-1 text-xs text-stone-500">Pemilik mengelola menu, stok, laporan, dan pengguna. Kasir hanya memakai halaman kasir.</p>
    @endif
    <x-input-error :messages="$errors->get('role')" class="mt-2" />
</div>
