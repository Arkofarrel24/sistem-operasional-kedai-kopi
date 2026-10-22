<x-owner-layout title="Pengguna">
    <x-slot:actions>
        <x-link-button href="{{ route('owner.users.create') }}">Tambah Pengguna</x-link-button>
    </x-slot:actions>

    @php
        // One column template for the header and every row, so badges and actions line up vertically.
        // The action column has three fixed slots: edit | reset password | (de)activate.
        $columns = 'sm:grid sm:grid-cols-[minmax(0,1fr)_6rem_6.5rem_20rem] sm:items-center sm:gap-x-4';
        $actionSlots = 'grid grid-cols-[3.5rem_9rem_6.5rem] items-center gap-x-1 text-sm';
    @endphp

    <div class="max-w-5xl">
        <x-card>
            <div class="{{ $columns }} hidden border-b border-stone-100 px-6 py-3 text-xs font-medium text-stone-500" aria-hidden="true">
                <span>Pengguna</span>
                <span>Peran</span>
                <span>Status</span>
                <span>Aksi</span>
            </div>

            <ul class="divide-y divide-stone-100">
                @foreach ($users as $user)
                    @php($isMe = $user->is(auth()->user()))
                    <li class="{{ $columns }} space-y-3 px-4 py-4 sm:space-y-0 sm:px-6">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span @class(['font-medium', 'text-stone-900' => $user->is_active, 'text-stone-500' => ! $user->is_active])>{{ $user->name }}</span>
                                @if ($isMe)
                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">Akun Anda</span>
                                @endif
                            </p>
                            <p class="truncate text-sm text-stone-500">{{ $user->email }}</p>
                        </div>

                        {{-- Phones show both badges on one line; from sm up each sits in its own column. --}}
                        <div class="flex gap-2 sm:contents">
                            <div>
                                <span @class([
                                    'inline-block rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-amber-100 text-amber-800' => $user->isOwner(),
                                    'bg-stone-100 text-stone-700' => ! $user->isOwner(),
                                ])>{{ $user->role->label() }}</span>
                            </div>
                            <div>
                                @if ($user->is_active)
                                    <span class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
                                @else
                                    <span class="inline-block rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Nonaktif</span>
                                @endif
                            </div>
                        </div>

                        <div class="{{ $actionSlots }}">
                            <a href="{{ route('owner.users.edit', $user) }}" class="font-medium text-amber-700 hover:underline">Ubah</a>
                            <a href="{{ route('owner.users.password.edit', $user) }}" class="font-medium text-stone-700 hover:text-stone-900 hover:underline">Reset kata sandi</a>
                            @if ($isMe)
                                {{-- An owner cannot deactivate their own account: no action here. --}}
                                <span></span>
                            @elseif ($user->is_active)
                                <x-confirm-form :action="route('owner.users.status', $user)" method="PATCH"
                                                :title="'Nonaktifkan '.$user->name.'?'"
                                                message="Pengguna ini tidak bisa masuk lagi dan sesinya yang sedang aktif akan keluar otomatis. Riwayat transaksinya tetap tersimpan, dan Anda bisa mengaktifkannya kembali kapan saja."
                                                confirm="Nonaktifkan">
                                    <input type="hidden" name="is_active" value="0">
                                    <button type="submit" class="font-medium text-red-600 hover:underline">Nonaktifkan</button>
                                </x-confirm-form>
                            @else
                                <form method="POST" action="{{ route('owner.users.status', $user) }}" novalidate>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="1">
                                    <button type="submit" class="font-medium text-emerald-700 hover:underline">Aktifkan</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <div class="mt-4">{{ $users->links() }}</div>
        <p class="mt-4 text-xs text-stone-600">Pengguna tidak dihapus karena transaksi dan riwayat stok mencatat namanya. Nonaktifkan pengguna yang sudah tidak bekerja.</p>
    </div>
</x-owner-layout>
