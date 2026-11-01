<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Enums\ScanAction;
use App\Enums\TransferStatus;
use App\Models\StockScan;
use App\Models\Transfer;
use App\Services\StockTrackingService;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app', ['plain' => true])]
#[Title('Receive transfer')]
class Receive extends Component
{
    public Transfer $transfer;

    /** @var array<int> ids of items scanned in so far */
    public array $scannedIds = [];

    /** @var array<string> codes of boxes scanned on arrival */
    public array $scannedBoxes = [];

    public string $note = '';

    public string $cancelReason = '';

    public ?array $flash = null;

    public function mount(Transfer $transfer): void
    {
        abort_if(Gate::denies('transfer_access'), 403);

        abort_unless(Branches::allows($transfer->from_warehouse_id) || Branches::allows($transfer->to_warehouse_id), 403);

        $this->transfer = $transfer;

        if ($transfer->status !== TransferStatus::IN_TRANSIT) {
            $this->scannedIds = $transfer->items()->wherePivotNotNull('received_at')->pluck('stock_items.id')->all();
        }
    }

    public function scanned(string $code): void
    {
        if ($this->transfer->status !== TransferStatus::IN_TRANSIT || ! Branches::canReceiveAt($this->transfer->to_warehouse_id)) {
            return;
        }

        $code = strtoupper(trim($code));

        $boxUnits = $this->boxUnits();

        // A box QR confirms the box arrived; the units inside are then scanned one by one.
        if (str_starts_with($code, 'BX-')) {
            if (! isset($boxUnits[$code])) {
                $this->flash = ['type' => 'error', 'message' => __('Box :code is not on this transfer – keep it aside and tell head office.', ['code' => $code])];

                return;
            }

            if (! in_array($code, $this->scannedBoxes, true)) {
                $this->scannedBoxes[] = $code;
            }

            $this->flash = ['type' => 'success', 'message' => __('Box :code arrived. Now open it and scan the :n units inside.', ['code' => $code, 'n' => count($boxUnits[$code])])];

            return;
        }

        $item = $this->transfer->items()->where('code', $code)->first();

        $boxCode = $item ? collect($boxUnits)->search(fn ($ids) => in_array($item->id, $ids, true)) : false;
        if ($item && $boxCode !== false && ! in_array($boxCode, $this->scannedBoxes, true)) {
            $this->flash = ['type' => 'error', 'message' => __(':code travelled in box :box. Scan the box first.', ['code' => $item->code, 'box' => $boxCode])];

            return;
        }

        if (! $item) {
            $this->flash = ['type' => 'error', 'message' => __(':code is not on this transfer – keep it aside and tell head office.', ['code' => $code])];

            return;
        }

        if (in_array($item->id, $this->scannedIds, true)) {
            $this->flash = ['type' => 'warning', 'message' => __(':code was already scanned.', ['code' => $item->code])];

            return;
        }

        $this->scannedIds[] = $item->id;
        $this->flash = ['type' => 'success', 'message' => __(':code received', ['code' => $item->code])];
    }

    /** @return array<string, array<int>> unit ids per box code, as packed at dispatch */
    private function boxUnits(): array
    {
        return StockScan::query()
            ->join('stock_boxes', 'stock_boxes.id', '=', 'stock_scans.box_id')
            ->where('stock_scans.transfer_id', $this->transfer->id)
            ->where('stock_scans.action', ScanAction::DISPATCHED)
            ->get(['stock_boxes.code', 'stock_scans.stock_item_id'])
            ->groupBy('code')
            ->map(fn ($rows) => $rows->pluck('stock_item_id')->map(fn ($id) => (int) $id)->all())
            ->all();
    }

    public function confirm(StockTrackingService $service): void
    {
        abort_unless(Branches::canReceiveAt($this->transfer->to_warehouse_id), 403, __('Only the receiving branch can confirm this delivery.'));

        try {
            $boxIds = \App\Models\StockBox::query()->whereIn('code', $this->scannedBoxes)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $service->receive($this->transfer, $this->scannedIds, $this->note ?: null, $boxIds);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        session()->flash('tracking', $this->transfer->status === TransferStatus::RECEIVED
            ? ['type' => 'success', 'message' => __('Transfer :ref received in full.', ['ref' => $this->transfer->reference])]
            : ['type' => 'warning', 'message' => __('Transfer :ref received with missing items – they have been flagged to management.', ['ref' => $this->transfer->reference])]);

        $this->redirectRoute('tracking.transfers', navigate: true);
    }

    /** The sending side (or a super admin) can cancel a dispatch until it is received. */
    private function canCancel(): bool
    {
        return $this->transfer->status === TransferStatus::IN_TRANSIT
            && Gate::allows('transfer_create')
            && Branches::allows($this->transfer->from_warehouse_id); // super admins are allowed everywhere
    }

    public function cancelDispatch(StockTrackingService $service): void
    {
        abort_unless($this->canCancel(), 403);

        $this->validate(['cancelReason' => ['required', 'string', 'min:3', 'max:300']], [], ['cancelReason' => __('reason')]);

        try {
            $service->cancelTransfer($this->transfer, $this->cancelReason);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        session()->flash('tracking', ['type' => 'success', 'message' => __('Dispatch :ref cancelled. Everything is back at :where.', ['ref' => $this->transfer->reference, 'where' => $this->transfer->fromWarehouse?->name])]);
        $this->redirect(route('tracking.transfers'), navigate: true);
    }

    public function render()
    {
        $this->transfer->loadMissing('fromWarehouse', 'toWarehouse', 'dispatcher', 'receiver');

        $units = $this->transfer->items()->with('product:id,name')->orderBy('stock_items.id')->get();
        $boxUnits = $this->boxUnits();
        $inBoxes = collect($boxUnits)->flatten()->all();

        return view('livewire.tracking.receive', [
            'boxes' => collect($boxUnits)->map(fn ($ids) => $units->whereIn('id', $ids)),
            'groups' => $units->whereNotIn('id', $inBoxes)->groupBy('product.name'),
            // Only the receiving branch scans a transfer in.
            'open' => $this->transfer->status === TransferStatus::IN_TRANSIT && Branches::canReceiveAt($this->transfer->to_warehouse_id),
            'awaitingBranch' => $this->transfer->status === TransferStatus::IN_TRANSIT && ! Branches::canReceiveAt($this->transfer->to_warehouse_id),
            'canCancel' => $this->canCancel(),
        ]);
    }
}
