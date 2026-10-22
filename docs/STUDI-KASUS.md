# Studi Kasus: Kopi Nusa

*Sistem kasir, stok, dan rekap penjualan untuk kedai kopi satu outlet.*

> Kopi Nusa adalah bisnis fiktif yang dirancang sebagai studi kasus. Masalahnya diambil dari pola yang umum di kedai kopi kecil; angka hasil berasal dari data demo 30 hari yang dibuat oleh seeder aplikasi.

## Masalah

Kopi Nusa adalah kedai kopi kecil di Surabaya dengan satu pemilik dan dua kasir yang merangkap barista. Mereka menjual kopi gelas, kopi botolan satu liter, dan minuman non-kopi, baik untuk pelanggan yang datang langsung maupun pesanan kantor dalam jumlah banyak.

Seluruh operasional berjalan di atas kertas dan chat: pesanan ditulis di nota atau diterima lewat WhatsApp, dan uang masuk laci tanpa catatan yang bisa dicocokkan. Tiga masalah terus berulang:

1. **Salah hitung dan catatan hilang.** Nota terselip, pesanan WhatsApp tertimbun, dan uang di laci sulit dicocokkan dengan penjualan.
2. **Kehabisan bahan saat ramai.** Tidak ada yang mencatat sisa susu, gula aren, atau cup. Kekurangan baru diketahui ketika pelanggan sudah mengantre.
3. **Rekap memakan waktu dan tetap buta.** Setiap minggu pemilik menghabiskan satu sampai dua jam menyalin nota ke spreadsheet. Setelah itu pun ia belum tahu menu terlaris, jam ramai, atau keuntungan setelah biaya bahan.

Odoo dan POS berlangganan dinilai terlalu mahal dan rumit untuk kedai sekecil ini. Yang dibutuhkan adalah alat yang bisa dipakai kasir di tablet tanpa pelatihan dan memberi pemilik gambaran bisnis tanpa menghitung sendiri.

## Analisis

Ketiga masalah itu ternyata berakar pada hal yang sama: **penjualan dan stok tidak terhubung**. Setiap gelas Es Kopi Susu yang terjual memakai 18 gram biji kopi, 150 ml susu, 20 ml gula aren, dan satu cup, tetapi hubungan itu hanya ada di kepala barista. Selama keduanya terpisah, stok hanya bisa ditebak dan laba kotor tidak bisa dihitung.

Dari situ muncul beberapa keputusan desain:

- **Resep sebagai penghubung.** Setiap menu diberi resep per porsi. Ketika transaksi tersimpan, stok setiap bahan berkurang sebesar resep dikali jumlah yang dibeli. Peringatan "perlu restock" lalu cukup membandingkan stok dengan batas minimum.
- **Catatan, bukan angka yang ditimpa.** Stok tidak disimpan sebagai satu angka yang diubah-ubah. Setiap perubahan (penjualan, restock, stock opname, bahan rusak, void) dicatat sebagai pergerakan stok dengan jumlah dan harga beli saat itu. Angka stok saat ini hanyalah ringkasan dari catatan tersebut dan bisa dicocokkan kapan saja.
- **Sejarah tidak boleh berubah.** Harga menu dan harga bahan pasti naik. Transaksi menyimpan nama dan harga menu saat dibeli, dan setiap pergerakan stok menyimpan harga beli saat itu. Karena itu laporan laba bulan lalu tetap sama walaupun harga kopi naik hari ini.
- **Pembatalan yang jujur.** Transaksi yang salah tidak dihapus. Pemilik membatalkannya (void) dengan alasan, stok dikembalikan persis seperti saat terjual, dan transaksi itu tidak dihitung di laporan tetapi tetap bisa ditelusuri.
- **Dua kasir, satu stok.** Dua kasir bisa menjual menu yang sama pada detik yang sama; stok tidak boleh terjual melebihi yang ada dan nomor invoice tidak boleh kembar.

## Solusi

Hasilnya adalah aplikasi web dengan dua peran.

**Kasir** membuka halaman POS di tablet: grid menu per kategori dengan tombol besar, pencarian, dan keranjang yang totalnya selalu terlihat. Catatan per item seperti "less sugar" bisa ditambahkan. Untuk pembayaran tunai tersedia tombol nominal cepat dan kembalian dihitung otomatis; QRIS dan transfer dicatat sebagai metode bayar. Setelah dibayar, struk bernomor invoice langsung muncul dan siap dicetak. Jika salah satu bahan tidak cukup, transaksi ditolak dengan pesan yang jelas, misalnya "Susu UHT kurang 100 ml", dan tidak ada data yang setengah tersimpan.

**Pemilik** mendapat dashboard yang menjawab pertanyaan pagi hari: berapa omzet hari ini dibanding hari yang sama minggu lalu, bahan apa yang perlu dibeli, dan menu apa yang sedang laris. Dari halaman stok ia mencatat restock beserta harga beli, stock opname, dan bahan rusak; riwayat tiap bahan menunjukkan setiap gram yang masuk dan keluar. Halaman laporan merangkum rentang tanggal mana pun: omzet per metode bayar, laba kotor dan margin, nilai bahan yang terbuang, grafik transaksi per jam, dan menu terlaris. Semuanya bisa diekspor ke Excel dalam satu klik.

Di balik layar, setiap transaksi beserta pengurangan stoknya disimpan dalam satu transaksi database. Bahan yang dipakai dikunci sebentar dalam urutan yang selalu sama, sehingga dua kasir yang menjual bersamaan antre dengan tertib alih-alih saling bertabrakan. Tes otomatis menjalankan beberapa checkout dan void yang benar-benar paralel terhadap MySQL, dan tes inilah yang menemukan dua bug yang tak terlihat saat dicoba manual: deadlock pada penomoran invoice dan kemungkinan void ganda. Keduanya sudah diperbaiki dan kini dijaga oleh tes.

## Hasil

Dengan data demo 30 hari (sekitar 1.000 transaksi, 11 menu, 11 bahan), aplikasi memberi gambaran yang sebelumnya butuh berjam-jam:

- **Rekap mingguan dari 1–2 jam menjadi kurang dari satu menit.** Memilih rentang tanggal langsung menampilkan omzet (sekitar Rp51,6 juta dalam 30 hari), jumlah transaksi, dan rata-rata belanja per transaksi (sekitar Rp48 ribu). File Excel siap kirim tersedia dengan satu klik.
- **Laba kotor yang bisa dipercaya.** Biaya bahan dihitung dari harga beli yang benar-benar dibayar. Untuk data demo, HPP sekitar Rp18,4 juta dengan margin kotor sekitar 64%.
- **Jam ramai dan menu terlaris terlihat jelas.** Grafik per jam menunjukkan puncak pukul 07.00–09.00 dan gelombang kedua pukul 16.00–18.00, berguna untuk mengatur jadwal kasir dan persiapan bahan. Es Kopi Susu Gula Aren menjadi menu terlaris dengan selisih jauh, disusul Americano dan Es Teh Manis.
- **Kehabisan bahan bisa diantisipasi.** Bahan yang turun di bawah batas minimum muncul di dashboard sebelum benar-benar habis, sehingga restock bisa dilakukan di luar jam sibuk.
- **Setiap angka bisa ditelusuri.** Transaksi yang dibatalkan tetap tercatat beserta alasannya, dan perintah pemeriksaan stok memastikan angka stok selalu sama dengan seluruh riwayat pergerakannya.

Bagi kedai sekecil ini, yang terpenting: kasir cukup mengetuk menu dan menerima uang seperti biasa, sementara pemilik mendapat catatan stok dan laporan tanpa administrasi tambahan.
