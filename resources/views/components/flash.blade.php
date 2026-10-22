{{-- Result of the last action: success (status) or failure (error). Dismissible; stays until closed. --}}
@foreach (['status' => 'success', 'error' => 'error'] as $key => $kind)
    @if (session($key))
        <div x-data="{ show: true }" x-show="show"
             x-transition:leave="transition duration-150 ease-out" x-transition:leave-end="opacity-0 -translate-y-1"
             role="{{ $kind === 'error' ? 'alert' : 'status' }}"
             @class([
                 'mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
                 'border-emerald-200 bg-emerald-50 text-emerald-900' => $kind === 'success',
                 'border-red-200 bg-red-50 text-red-900' => $kind === 'error',
             ])>
            @if ($kind === 'success')
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
            @else
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
            @endif
            <p class="min-w-0 flex-1">{{ session($key) }}</p>
            <button type="button" x-on:click="show = false" class="-m-1 shrink-0 rounded-md p-1 opacity-60 hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-current" aria-label="Tutup pesan">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
            </button>
        </div>
    @endif
@endforeach
