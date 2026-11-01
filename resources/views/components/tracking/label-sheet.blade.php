@props(['items', 'type' => 'barcode', 'standalone' => true])

{{-- One label per unit: a barcode by default (QR is used for boxes). --}}
<div @class(['grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3', 'print-area' => $standalone])>
    @foreach ($items as $item)
        <div wire:key="label-{{ $item->id }}" class="border border-dashed border-gray-300 rounded p-2 flex flex-col items-center text-center break-inside-avoid bg-white">
            @if ($type === 'qr')
                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(96)->margin(0)->generate($item->code) !!}
            @else
                {!! \Milon\Barcode\Facades\DNS1DFacade::getBarcodeSVG($item->code, 'C128', 1.4, 48, 'black', false) !!}
            @endif
            <div class="mt-1 text-[11px] font-mono font-semibold text-gray-900">{{ $item->code }}</div>
            <div class="text-[10px] text-gray-600 leading-tight">{{ \Illuminate\Support\Str::limit($item->product?->name, 28) }}</div>
        </div>
    @endforeach
</div>
