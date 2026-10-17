<x-owner-layout title="Dashboard">
    <div class="rounded-xl border border-stone-200 bg-white p-6">
        <h2 class="text-xl font-semibold">Selamat datang, {{ auth()->user()->name }}</h2>
        <p class="mt-1 text-sm text-stone-600">
            Ringkasan omzet, peringatan restock, dan grafik penjualan akan tampil di sini.
        </p>
    </div>
</x-owner-layout>
