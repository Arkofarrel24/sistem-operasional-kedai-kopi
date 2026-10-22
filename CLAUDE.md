# CLAUDE.md

## Proyek
Sistem operasional kedai kopi (POS + manajemen stok + rekap penjualan) untuk bisnis fiktif "Kopi Nusa". Ini project demo portofolio berbasis studi kasus.

**`PLAN.md` adalah acuan utama.** Baca sebelum mengerjakan apa pun. Studi kasus, fitur MVP, aturan bisnis, ERD, dan fase pengerjaan ada di sana. Jika permintaan bertentangan dengan PLAN.md, tanyakan dulu.

## Stack
- Laravel (stabil terbaru), PHP 8.2+, MySQL
- Laravel Breeze (Blade) untuk auth
- Blade + Livewire + Tailwind CSS
- Chart.js untuk grafik, maatwebsite/excel untuk ekspor
- Pest untuk tes
- Lingkungan pengembangan: macOS

## Cara kerja
- Kerjakan **satu fase per sesi**, sesuai urutan di PLAN.md bagian 8. Jangan mengerjakan fase berikutnya tanpa diminta.
- Untuk pekerjaan yang menyentuh banyak file, jelaskan rencana singkat dulu sebelum menulis kode.
- **Jangan menambah fitur di luar MVP** (lihat "Di luar MVP" di PLAN.md).
- Setelah perubahan, jalankan tes yang relevan dan pastikan lulus sebelum menyatakan selesai.
- Di akhir fase, ringkas apa yang dikerjakan, cara mengeceknya, dan sarankan pesan commit. Jangan commit atau push sendiri kecuali diminta.
- Jika ada keputusan desain yang tidak tercakup PLAN.md, pilih opsi paling sederhana dan sebutkan di ringkasan.

## Konvensi kode
- Kode, nama variabel, tabel, dan kolom dalam **bahasa Inggris**. Teks yang tampil di UI dalam **bahasa Indonesia**.
- Logika bisnis (transaksi, stok, void, laporan) di **service class** (`app/Services/`), bukan di controller atau komponen Livewire.
- Validasi input dengan Form Request atau validasi Livewire; jangan percaya input dari client.
- Uang dan stok disimpan sebagai `decimal`, bukan `float`.
- Gunakan Eloquent relationship sesuai ERD; hindari query N+1 (eager loading).
- Otorisasi peran lewat middleware/policy, bukan pengecekan manual di view.
- Format tampilan: Rupiah `Rp15.000`, tanggal `17 Okt 2026`, zona waktu `Asia/Jakarta`.

## Aturan bisnis yang tidak boleh dilanggar
1. Penyimpanan transaksi + pengurangan stok dalam satu `DB::transaction`.
2. Stok tidak cukup → transaksi ditolak, tidak ada data tersimpan.
3. Setiap perubahan stok dicatat di `stock_movements`; `ingredients.current_stock` hanya diubah lewat `StockService`.
4. `sale_items` menyimpan snapshot nama dan harga menu.
5. Void tidak menghapus data; stok dikembalikan lewat movement `void_return`.

## Perintah umum
```bash
php artisan serve
npm run dev
php artisan migrate:fresh --seed
php artisan test
```

## Akun demo (setelah seeder)
- Owner: `owner@demo.test` / `password`
- Kasir: `kasir1@demo.test`, `kasir2@demo.test` / `password`
- Kasir nonaktif: `kasir3@demo.test` / `password` (tidak bisa login; untuk demo manajemen pengguna)
