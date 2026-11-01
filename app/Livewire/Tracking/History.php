<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Enums\ScanAction;
use App\Models\Product;
use App\Models\StockScan;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['plain' => true])]
#[Title('Movement history')]
class History extends Component
{
    use WithPagination;

    #[Url]
    public string $product = '';

    #[Url]
    public string $warehouse = '';

    #[Url]
    public string $action = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_if(Gate::denies('transfer_access'), 403);

        $scans = Branches::scope(StockScan::query())
            ->with('product:id,name,code', 'warehouse:id,name', 'user:id,name', 'transfer:id,reference')
            ->when($this->product !== '', fn ($q) => $q->where('product_id', $this->product))
            ->when($this->warehouse !== '', fn ($q) => $q->where('warehouse_id', $this->warehouse))
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->latest('id')
            ->paginate(25);

        return view('livewire.tracking.history', [
            'scans' => $scans,
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'warehouses' => Branches::warehouses(),
            'actions' => ScanAction::cases(),
        ]);
    }
}
