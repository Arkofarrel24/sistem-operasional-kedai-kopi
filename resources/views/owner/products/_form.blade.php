<div>
    <x-input-label for="name" value="Nama menu" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $product->name)" required />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <x-input-label for="category_id" value="Kategori" />
        <x-select-input id="category_id" name="category_id" class="mt-1 block w-full" required>
            <option value="">Pilih kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </x-select-input>
        <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="price" value="Harga jual (Rp)" />
        <x-text-input id="price" name="price" type="number" min="0" step="1" inputmode="numeric" class="mt-1 block w-full"
                      :value="old('price', $product->price !== null ? (float) $product->price : null)" required />
        <x-input-error :messages="$errors->get('price')" class="mt-2" />
    </div>
</div>

<div>
    <x-input-label for="image" value="Foto (opsional)" />
    @if ($product->image_url)
        <img src="{{ $product->image_url }}" alt="Foto {{ $product->name }}" class="mt-2 h-24 w-24 rounded-lg object-cover">
    @endif
    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
           class="mt-2 block w-full text-sm text-stone-600 file:mr-3 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-stone-200">
    <p class="mt-1 text-xs text-stone-500">JPG, PNG, atau WebP, maksimal 2 MB.{{ $product->image ? ' Unggah foto baru untuk mengganti.' : '' }}</p>
    <x-input-error :messages="$errors->get('image')" class="mt-2" />
</div>

<label class="flex items-center gap-2">
    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
           @checked(old('is_active', $product->is_active))>
    <span class="text-sm">Aktif (tampil di kasir jika resep sudah diisi)</span>
</label>
<x-input-error :messages="$errors->get('is_active')" class="mt-2" />
