# PLAN — Sistem Operasional Kedai Kopi (POS + Stok + Rekap)

Project demo portofolio berbasis studi kasus. Dokumen ini adalah acuan utama pengembangan.
Saat mengerjakan di Claude Code: baca file ini dulu, kerjakan per fase, dan jangan menambah fitur di luar MVP tanpa diminta.

---

## 1. Studi Kasus

**Bisnis (fiktif):** Kopi Nusa — kedai kopi kecil di Surabaya, 1 outlet, 1 pemilik + 2 kasir/barista. Menjual kopi gelas, kopi botolan 1 liter, dan non-kopi. Menerima pesanan langsung dan pesanan kantor dalam jumlah banyak.

**Masalah (as-is):**
1. Transaksi dicatat di buku/nota manual dan chat WhatsApp, rawan salah hitung dan hilang.
2. Stok bahan (biji kopi, susu, gula aren, cup, botol) tidak tercatat; sering kehabisan saat ramai.
3. Pemilik butuh 1–2 jam per minggu untuk merekap penjualan, dan tidak tahu menu terlaris atau jam ramai.
4. Aplikasi seperti Odoo atau POS berlangganan dianggap terlalu mahal dan rumit untuk skala ini.

**Solusi (to-be):**
1. Kasir mencatat transaksi di aplikasi POS berbasis web (bisa di tablet/HP).
2. Setiap menu punya resep; transaksi otomatis mengurangi stok bahan.
3. Dashboard memberi peringatan bahan yang di bawah stok minimum.
4. Laporan penjualan harian/bulanan dibuat otomatis dan bisa diekspor ke Excel.

**Metrik keberhasilan (untuk halaman studi kasus):**
- Waktu rekap mingguan: ±1–2 jam → < 1 menit
- Kehabisan bahan mendadak: dapat diantisipasi lewat peringatan restock
- Pemilik dapat melihat menu terlaris dan jam ramai tanpa hitung manual

---

## 2. Pengguna & Hak Akses

| Peran | Akses |
|---|---|
| **Owner** | Semua fitur: dashboard, laporan, kelola menu, resep, bahan, stok, pengguna, void transaksi |
| **Kasir** | Halaman POS, riwayat transaksi miliknya hari ini, lihat stok (read-only) |

---

## 3. Fitur MVP

### F1. Autentikasi & peran
- Login email + password, logout.
- Middleware peran (`owner`, `cashier`). Kasir yang membuka halaman owner → 403.

### F2. Master data (Owner)
- **Kategori menu** (Kopi, Non-Kopi, Botolan, dll): CRUD.
- **Menu/produk**: nama, kategori, harga jual, foto (opsional), status aktif/nonaktif.
- **Bahan**: nama, satuan dasar (`gram`, `ml`, `pcs`), stok saat ini, stok minimum, harga beli per satuan.
- **Resep**: per menu, daftar bahan + jumlah per 1 porsi. Contoh: Es Kopi Susu = 18 g biji kopi, 150 ml susu, 20 ml gula aren, 1 pcs cup.

### F3. POS / Kasir
- Grid menu aktif per kategori, pencarian menu.
- Keranjang: tambah/kurang qty, catatan per item (mis. "less sugar", "nama: Budi").
- Total otomatis. Metode bayar: `cash`, `qris`, `transfer` (dicatat manual, tanpa integrasi pembayaran).
- Untuk `cash`: input uang diterima → kembalian otomatis.
- Simpan transaksi → nomor invoice otomatis (format `INV-YYYYMMDD-0001`) → tampilan struk yang bisa dicetak.

### F4. Stok
- Pengurangan stok otomatis saat transaksi tersimpan (berdasarkan resep × qty).
- **Restock** (Owner): tambah stok bahan, catat harga beli dan catatan supplier.
- **Penyesuaian/waste** (Owner): koreksi stok hasil stock opname atau bahan rusak, wajib isi alasan.
- Riwayat pergerakan stok per bahan (masuk, keluar karena penjualan, penyesuaian, waste).

### F5. Dashboard & peringatan (Owner)
- Ringkasan hari ini: omzet, jumlah transaksi, rata-rata nilai transaksi.
- Daftar bahan di bawah stok minimum (peringatan restock).
- Grafik omzet 7/30 hari terakhir.

### F6. Laporan (Owner)
- Filter rentang tanggal.
- Rekap penjualan: omzet, jumlah transaksi, per metode pembayaran.
- Menu terlaris (qty dan omzet).
- Penjualan per jam (untuk melihat jam ramai).
- Estimasi HPP dan laba kotor (dari resep × harga beli bahan).
- Ekspor ke Excel.

### F7. Void transaksi (Owner)
- Transaksi dapat dibatalkan dengan alasan; stok bahan dikembalikan otomatis. Transaksi void tidak dihapus dan tidak dihitung di laporan.

### Di luar MVP (jangan dikerjakan dulu)
Integrasi pembayaran online, multi-outlet, order online pelanggan, manajemen supplier lengkap, shift kasir, diskon/promo, printer thermal via Bluetooth.

---

## 4. Aturan Bisnis

1. Semua stok disimpan dalam **satuan dasar** (`gram`, `ml`, `pcs`) sebagai desimal.
2. Penyimpanan transaksi dan pengurangan stok dilakukan dalam **satu database transaction**; jika gagal, semua dibatalkan.
3. Jika stok bahan tidak cukup untuk transaksi, **transaksi ditolak** dengan pesan bahan apa yang kurang.
4. Harga dan nama menu di `sale_items` adalah **snapshot** saat transaksi; perubahan harga menu tidak mengubah transaksi lama.
5. Setiap perubahan stok wajib tercatat di `stock_movements`. Kolom `ingredients.current_stock` hanya diubah melalui service stok, bukan langsung dari controller.
6. Menu tanpa resep tidak bisa dijual (tidak muncul di POS) — mencegah stok tidak terlacak.
7. Menu nonaktif tidak muncul di POS tetapi tetap ada di laporan historis.
8. Bahan dapat dinonaktifkan (`ingredients.is_active`). Menu yang resepnya memakai minimal satu bahan nonaktif tidak muncul di POS dan ditolak saat checkout, tetapi tetap ada di laporan historis.

---

## 5. ERD

```mermaid
erDiagram
    users ||--o{ sales : "mencatat"
    users ||--o{ stock_movements : "mencatat"
    categories ||--o{ products : "mengelompokkan"
    products ||--o{ recipes : "punya"
    ingredients ||--o{ recipes : "dipakai di"
    sales ||--|{ sale_items : "berisi"
    products ||--o{ sale_items : "terjual sebagai"
    ingredients ||--o{ stock_movements : "bergerak"
    sales ||--o{ stock_movements : "memicu"

    users {
        bigint id PK
        string name
        string email UK
        string password
        enum role "owner, cashier"
        timestamps
    }
    categories {
        bigint id PK
        string name
        timestamps
    }
    products {
        bigint id PK
        bigint category_id FK
        string name
        decimal price
        string image "nullable"
        boolean is_active
        timestamps
    }
    ingredients {
        bigint id PK
        string name
        enum unit "gram, ml, pcs"
        decimal current_stock
        decimal min_stock
        decimal cost_per_unit
        boolean is_active
        timestamps
    }
    recipes {
        bigint id PK
        bigint product_id FK
        bigint ingredient_id FK
        decimal quantity "per 1 porsi"
        timestamps
    }
    sales {
        bigint id PK
        string invoice_no UK
        bigint user_id FK
        decimal total
        enum payment_method "cash, qris, transfer"
        decimal paid_amount
        decimal change_amount
        enum status "completed, void"
        string void_reason "nullable"
        timestamp voided_at "nullable"
        timestamps
    }
    sale_items {
        bigint id PK
        bigint sale_id FK
        bigint product_id FK
        string product_name "snapshot"
        decimal unit_price "snapshot"
        int quantity
        decimal subtotal
        string note "nullable"
        timestamps
    }
    stock_movements {
        bigint id PK
        bigint ingredient_id FK
        bigint user_id FK
        bigint sale_id FK "nullable"
        enum type "restock, sale, void_return, adjustment, waste"
        decimal quantity "positif masuk, negatif keluar"
        decimal unit_cost "nullable, untuk restock"
        string note "nullable"
        timestamps
    }
```

Catatan desain:
- `recipes` memiliki unique index `(product_id, ingredient_id)`.
- `stock_movements` adalah sumber kebenaran; `current_stock` adalah cache yang harus sama dengan `SUM(quantity)` per bahan. Sediakan perintah artisan untuk mengecek konsistensinya.
- Index pada `sales.created_at` dan `sales.status` untuk laporan.
- Nomor invoice diambil dari tabel bantu `invoice_sequences` (`date` PK, `last_number`): satu baris penghitung per hari (zona Asia/Jakarta). Baris hari itu dibuat sebelum transaksi checkout dimulai; di dalam transaksi penghitung dinaikkan dengan satu `UPDATE` yang mengunci baris itu sampai commit, sehingga checkout bersamaan antre dan tidak pernah mendapat nomor yang sama. Ditambah unique index pada `sales.invoice_no`. Checkout yang gagal ikut me-rollback penghitungnya, sehingga nomor tidak bolong.

---

## 6. Stack

- **Backend:** Laravel (versi stabil terbaru), PHP 8.2+
- **Database:** MySQL
- **Auth:** Laravel Breeze (Blade)
- **Frontend:** Blade + Livewire (halaman POS dan keranjang), Tailwind CSS
- **Grafik:** Chart.js
- **Ekspor Excel:** maatwebsite/excel
- **Tes:** Pest atau PHPUnit (fitur inti: transaksi, stok, void)

Struktur yang disarankan: logika stok dan transaksi di service class (`app/Services/SaleService.php`, `app/Services/StockService.php`), bukan di controller/komponen Livewire.

---

## 7. Data Dummy (Seeder)

- 1 owner (`owner@demo.test`) dan 2 kasir (`kasir1@demo.test`, `kasir2@demo.test`), password `password`.
- 4 kategori, ±15 menu dengan harga realistis (Rp15.000–Rp95.000 untuk botolan 1 L).
- ±12 bahan dengan resep lengkap untuk semua menu.
- ±30 hari transaksi dummy dengan pola realistis: ramai jam 07–09 dan 15–17, akhir pekan lebih ramai, 2–3 bahan sengaja di bawah stok minimum.
- Seeder transaksi harus melewati `SaleService` agar stok dan `stock_movements` konsisten.

---

## 8. Fase Pengerjaan

| Fase | Isi | Selesai jika |
|---|---|---|
| **0. Setup** | Proyek Laravel, Breeze, Tailwind, Livewire, koneksi MySQL, layout dasar | Login berjalan, layout owner & kasir berbeda |
| **1. Master data** | Migrasi + model + CRUD kategori, menu, bahan, resep; middleware peran | Owner bisa input menu lengkap dengan resep |
| **2. POS** | Halaman kasir, keranjang, pembayaran, invoice, struk; `SaleService` + `StockService` | Transaksi tersimpan dan stok berkurang sesuai resep; stok kurang → ditolak |
| **3. Stok** | Restock, penyesuaian/waste, riwayat pergerakan, void transaksi | Semua perubahan stok tercatat; void mengembalikan stok |
| **4. Dashboard & laporan** | Ringkasan, peringatan restock, grafik, laporan + ekspor Excel | Angka laporan cocok dengan data transaksi |
| **5. Seeder & tes** | Data dummy 30 hari, tes fitur inti | `php artisan migrate:fresh --seed` menghasilkan demo yang hidup; tes lulus |
| **6. Deploy & dokumentasi** | Deploy demo online, README, akun demo | Bisa dibuka dari HP dengan akun demo |

Tes minimal yang wajib ada:
1. Transaksi mengurangi stok sesuai resep × qty.
2. Transaksi dengan stok tidak cukup ditolak dan tidak ada data yang tersimpan.
3. Void mengembalikan stok dan transaksi tidak masuk laporan.
4. Kasir tidak bisa mengakses halaman owner.

---

## 9. Materi Portofolio (dikerjakan setelah aplikasi jadi)

- Halaman studi kasus: masalah → solusi → alur as-is/to-be → ERD → screenshot → metrik.
- Video demo singkat (1–2 menit): transaksi di POS → stok berkurang → peringatan restock → laporan.
- Link demo online + akun demo owner dan kasir.
