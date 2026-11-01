<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['plain' => true])]
#[Title('Transfers')]
class Transfers extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $warehouse = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_if(Gate::denies('transfer_access'), 403);

        $transfers = Branches::scopeTransfers(Transfer::query())
            ->with('fromWarehouse', 'toWarehouse', 'dispatcher')
            ->whereNotNull('dispatched_at')
            ->when($this->status !== '', fn ($q) => $q->where('status', (int) $this->status))
            ->when($this->warehouse !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('from_warehouse_id', $this->warehouse)
                ->orWhere('to_warehouse_id', $this->warehouse)))
            ->latest('dispatched_at')
            ->paginate(15);

        return view('livewire.tracking.transfers', [
            'transfers' => $transfers,
            'statuses' => TransferStatus::cases(),
            'warehouses' => Branches::warehouses(),
        ]);
    }
}
