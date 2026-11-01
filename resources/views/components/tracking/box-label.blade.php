@props(['box'])

{{-- Box label: a QR to scan the whole box, plus what is inside. --}}
<div class="inline-flex items-center gap-4 border-2 border-gray-900 rounded-lg p-3 bg-white text-gray-900 break-inside-avoid max-w-full">
    <div class="shrink-0">{!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->margin(0)->generate($box->code) !!}</div>
    <div class="min-w-0">
        <div class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">{{ __('Box') }}</div>
        <div class="font-mono text-xl font-bold">{{ $box->code }}</div>
        <div class="mt-1 text-xs leading-snug">{{ $box->summary() }}</div>
        <div class="mt-1 text-[10px] text-gray-500">{{ $box->warehouse?->name }} · {{ $box->created_at?->format('d M Y') }}</div>
    </div>
</div>
