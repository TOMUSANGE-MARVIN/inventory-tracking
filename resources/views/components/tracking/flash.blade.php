@props(['flash' => null])

@if ($flash)
    <div @class([
        'rounded-lg px-4 py-3 text-sm font-medium',
        'bg-green-100 text-green-800' => $flash['type'] === 'success',
        'bg-amber-100 text-amber-800' => $flash['type'] === 'warning',
        'bg-red-100 text-red-800' => $flash['type'] === 'error',
    ])>
        {{ $flash['message'] }}
    </div>
@endif
