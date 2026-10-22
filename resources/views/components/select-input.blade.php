@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'border-stone-300 focus:border-amber-500 focus:ring-amber-500 rounded-md shadow-sm']) }}>
    {{ $slot }}
</select>
