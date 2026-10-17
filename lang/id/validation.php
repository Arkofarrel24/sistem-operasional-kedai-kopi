<?php

// Rules not listed here fall back to the English messages.
return [
    'boolean' => ':Attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'decimal' => ':Attribute harus memiliki :decimal angka desimal.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'enum' => ':Attribute yang dipilih tidak valid.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'gt' => [
        'numeric' => ':Attribute harus lebih besar dari :value.',
    ],
    'gte' => [
        'numeric' => ':Attribute harus lebih besar dari atau sama dengan :value.',
    ],
    'image' => ':Attribute harus berupa gambar.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => ':Attribute maksimal berisi :max item.',
        'file' => ':Attribute maksimal :max kilobita.',
        'numeric' => ':Attribute maksimal :max.',
        'string' => ':Attribute maksimal :max karakter.',
    ],
    'mimes' => ':Attribute harus berformat: :values.',
    'min' => [
        'file' => ':Attribute minimal :min kilobita.',
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'numeric' => ':Attribute harus berupa angka.',
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah digunakan.',
    'uploaded' => ':Attribute gagal diunggah.',

    'attributes' => [
        'category_id' => 'kategori',
        'cost_per_unit' => 'harga beli per satuan',
        'email' => 'email',
        'image' => 'foto',
        'ingredient_id' => 'bahan',
        'initial_stock' => 'stok awal',
        'is_active' => 'status',
        'min_stock' => 'stok minimum',
        'name' => 'nama',
        'password' => 'kata sandi',
        'price' => 'harga',
        'quantity' => 'jumlah',
        'unit' => 'satuan',
    ],
];
