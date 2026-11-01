<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\SaleDetails;
use App\Services\ProductBoxHistory;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * One product over a period: the boxes it was recorded in, everything that
 * happened to each box, and its unit movements and sales.
 */
#[Layout('layouts.app', ['plain' => true])]
#[Title('Product analytics')]
class ProductAnalytics extends Component
{
    use WithPagination;

    #[Url(as: 'product')]
    public string $productId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public string $search = '';

    /** boxes | loose – which list is shown under the headline figures. */
    #[Url]
    public string $section = '';

    public function mount(): void
    {
        $this->from = $this->validDate($this->from) ?? today()->subDays(29)->toDateString();
        $this->to = $this->validDate($this->to) ?? today()->toDateString();

        if ($this->productId !== '' && ! Product::query()->whereKey($this->productId)->exists()) {
            $this->productId = '';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['productId', 'from', 'to'], true)) {
            $this->resetPage('boxes');
            $this->resetPage('loose');
        }

        if ($property === 'productId') {
            $this->section = '';
        }
    }

    public function pick(string $productId): void
    {
        $this->productId = $productId;
        $this->search = '';
        $this->section = '';
        $this->resetPage('boxes');
        $this->resetPage('loose');
    }

    /** Quick ranges: this week, this month, last month, last 30 days, this year. */
    public function range(string $preset): void
    {
        [$from, $to] = match ($preset) {
            'week' => [today()->startOfWeek(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'month' => [today()->startOfMonth(), today()],
            'year' => [today()->startOfYear(), today()],
            default => [today()->subDays(29), today()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
        $this->resetPage('boxes');
    }

    private function validDate(string $value): ?string
    {
        try {
            return $value !== '' ? Carbon::parse($value)->toDateString() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(): array
    {
        $from = Carbon::parse($this->validDate($this->from) ?? today()->subDays(29));
        $to = Carbon::parse($this->validDate($this->to) ?? today());

        return [$from->min($to), $to->max($from)];
    }

    #[Computed]
    public function product(): ?Product
    {
        return $this->productId !== '' ? Product::query()->with('category:id,name')->find($this->productId) : null;
    }

    #[Computed]
    public function matches()
    {
        return Product::query()
            ->select('id', 'name', 'code')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->limit(30)
            ->get();
    }

    private function sales(Carbon $from, Carbon $to): array
    {
        $row = SaleDetails::query()
            ->where('product_id', $this->productId)
            ->whereHas('sale', fn ($q) => $q->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->where('status', '!=', SaleStatus::CANCELED->value))
            ->selectRaw('COALESCE(SUM(quantity), 0) as qty, COALESCE(SUM(CASE WHEN sub_total > 0 THEN sub_total ELSE price * quantity END), 0) as revenue')
            ->first();

        return ['qty' => (int) $row->qty, 'revenue' => (float) $row->revenue];
    }

    public function render()
    {
        [$from, $to] = $this->period();
        $product = $this->product;
        $history = $product ? new ProductBoxHistory((string) $product->id, $from, $to) : null;
        $boxes = $history?->boxes();
        $loose = $history?->looseUnits();

        // Show loose items straight away for products that never came in boxes.
        $shown = in_array($this->section, ['boxes', 'loose'], true)
            ? $this->section
            : ($boxes && $boxes->total() === 0 && $loose->total() > 0 ? 'loose' : 'boxes');

        return view('livewire.analytics.product-analytics', [
            'shown' => $shown,
            'loose' => $loose,
            'looseDetails' => $loose && $shown === 'loose' ? $history->looseDetails($loose->items()) : collect(),
            'product' => $product,
            'periodFrom' => $from,
            'periodTo' => $to,
            'summary' => $history?->summary(),
            'boxes' => $boxes,
            'details' => $boxes ? $history->details($boxes->items()) : collect(),
            'sales' => $product ? $this->sales($from, $to) : null,
        ]);
    }
}
