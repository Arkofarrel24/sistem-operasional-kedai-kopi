@props([
    'action',
    'method' => 'POST',
    'title',
    'message',
    'confirm',
    'danger' => true,
    'when' => 'true',
])

{{--
    A form that asks for confirmation before an action that cannot be undone. The trigger is a normal
    submit button in the slot; submitting opens the dialog, and only "confirm" really submits.
    Without JavaScript the form simply submits. The dialog is teleported to <body> so table cells or
    animated cards never clip it. `when` is a JS expression: only ask when it is true (e.g. a non-empty cart).
--}}
<form method="POST" action="{{ $action }}" novalidate x-data="{ open: false }" x-ref="form"
      x-on:submit="if ({{ $when }}) { $event.preventDefault(); open = true }" {{ $attributes }}>
    @csrf
    @unless (strtoupper($method) === 'POST')
        @method($method)
    @endunless

    {{ $slot }}

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="alertdialog" aria-modal="true" aria-labelledby="confirm-title-{{ $id = uniqid() }}" aria-describedby="confirm-message-{{ $id }}"
             x-on:keydown.escape.window="open = false"
             x-effect="open && $nextTick(() => $refs.cancel.focus())">
            <div class="absolute inset-0 bg-stone-900/50" x-show="open"
                 x-transition:enter="transition-opacity duration-200 ease-out" x-transition:enter-start="opacity-0"
                 x-transition:leave="transition-opacity duration-150 ease-out" x-transition:leave-end="opacity-0"
                 x-on:click="open = false"></div>

            <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl ring-1 ring-stone-900/5" x-show="open"
                 x-transition:enter="transition duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]" x-transition:enter-start="opacity-0 scale-[0.96]"
                 x-transition:leave="transition duration-150 ease-out" x-transition:leave-end="opacity-0 scale-[0.98]">
                <h2 id="confirm-title-{{ $id }}" class="text-base font-semibold text-stone-900">{{ $title }}</h2>
                <p id="confirm-message-{{ $id }}" class="mt-2 text-sm leading-relaxed text-stone-600">{{ $message }}</p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-secondary-button x-ref="cancel" x-on:click="open = false">Batal</x-secondary-button>
                    <button type="button" x-on:click="open = false; $refs.form.submit()"
                            @class([
                                'press inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
                                'bg-red-600 hover:bg-red-500 focus-visible:ring-red-500' => $danger,
                                'bg-stone-900 hover:bg-stone-700 focus-visible:ring-amber-500' => ! $danger,
                            ])>{{ $confirm }}</button>
                </div>
            </div>
        </div>
    </template>
</form>
