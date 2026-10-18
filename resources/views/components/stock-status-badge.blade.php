@props(['status'])

<span @class([
    'rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-emerald-100 text-emerald-800' => $status === \App\Enums\StockStatus::Ok,
    'bg-amber-100 text-amber-800' => $status === \App\Enums\StockStatus::Low,
    'bg-red-100 text-red-700' => $status === \App\Enums\StockStatus::Out,
])>{{ $status->label() }}</span>
